<?php

namespace App\Services;

use App\Models\ClassForm;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CoverageService
{
    public function currentCoverage(ClassForm $classForm): int
    {
        return (int) Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('status', 'valid')
            ->sum('coverage_weight');
    }

    public function stats(ClassForm $classForm): array
    {
        $base = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('status', 'valid');

        $byChoice = (clone $base)
            ->selectRaw('choice, COUNT(*) as total')
            ->groupBy('choice')
            ->pluck('total', 'choice')
            ->map(fn ($v) => (int) $v)
            ->all();

        $classForm->loadMissing('form');
        $choices = $classForm->form?->resolvedOptions()['choices'] ?? [];
        $choiceCounts = [];
        foreach ($choices as $choice) {
            $value = $choice['value'];
            $choiceCounts[] = [
                'value' => $value,
                'label' => $choice['label'],
                'count' => (int) ($byChoice[$value] ?? 0),
            ];
        }
        foreach ($byChoice as $value => $count) {
            if (! collect($choiceCounts)->contains(fn ($c) => $c['value'] === $value)) {
                $choiceCounts[] = [
                    'value' => (string) $value,
                    'label' => $classForm->form?->choiceLabel((string) $value) ?? (string) $value,
                    'count' => (int) $count,
                ];
            }
        }

        return [
            'coverage' => (int) (clone $base)->sum('coverage_weight'),
            'agree' => (int) ($byChoice['agree'] ?? $byChoice['yes'] ?? 0),
            'disagree' => (int) ($byChoice['disagree'] ?? $byChoice['no'] ?? 0),
            'total' => (int) (clone $base)->count(),
            'by_choice' => $byChoice,
            'choice_counts' => $choiceCounts,
        ];
    }

    public function isAcceptingNewVotes(ClassForm $classForm): bool
    {
        if ($classForm->status !== 'open') {
            return false;
        }

        return (bool) $classForm->form?->isActive();
    }

    public function canParentChangeVote(ClassForm $classForm): bool
    {
        if ($classForm->status === 'closed') {
            return false;
        }

        return (bool) $classForm->form?->isActive();
    }

    /**
     * Teacher may delete own paper notes/responses while class vote is open and Form still active.
     */
    public function canTeacherEditPaperArtifacts(ClassForm $classForm): bool
    {
        $classForm->loadMissing('form');

        if ($classForm->status === 'closed') {
            return false;
        }

        return (bool) $classForm->form?->isActive();
    }

    /**
     * Void a valid response and reopen class_form if coverage drops below quota.
     */
    public function voidResponse(Response $response): void
    {
        DB::transaction(function () use ($response) {
            /** @var Response $locked */
            $locked = Response::query()->whereKey($response->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'valid') {
                return;
            }

            $locked->update(['status' => 'void']);

            /** @var ClassForm $classForm */
            $classForm = ClassForm::query()->whereKey($locked->class_form_id)->lockForUpdate()->firstOrFail();
            if ($classForm->status === 'closed') {
                return;
            }

            $coverage = $this->currentCoverage($classForm);
            $quota = (int) $classForm->classProfile()->value('quota');
            if ($classForm->status === 'quota_full' && $coverage < $quota) {
                $classForm->update(['status' => 'open']);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createResponseAtomic(ClassForm $classForm, array $attributes, int $weight = 1): Response
    {
        return DB::transaction(function () use ($classForm, $attributes, $weight) {
            /** @var ClassForm $locked */
            $locked = ClassForm::query()->whereKey($classForm->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('form');

            if ($locked->status === 'closed' || ! $locked->form?->isActive()) {
                throw new RuntimeException('Form đã đóng hoặc hết hạn, không nhận thêm phiếu.');
            }

            if ($locked->status === 'quota_full') {
                throw new RuntimeException('Đã đủ sĩ số (coverage), không thể ghi nhận thêm phiếu mới.');
            }

            if ($locked->status !== 'open') {
                throw new RuntimeException('Lớp đã khóa hoặc đóng, không nhận thêm phiếu.');
            }

            $quota = (int) $locked->classProfile()->value('quota');
            $current = $this->currentCoverage($locked);

            if ($current + $weight > $quota) {
                throw new RuntimeException('Đã đủ sĩ số (coverage), không thể ghi nhận thêm phiếu.');
            }

            $response = Response::query()->create(array_merge($attributes, [
                'class_form_id' => $locked->id,
                'coverage_weight' => $weight,
                'status' => 'valid',
            ]));

            if ($current + $weight >= $quota) {
                $locked->update(['status' => 'quota_full']);
            }

            return $response;
        });
    }

    public function updateParentChoice(ClassForm $classForm, string $zaloUserId, string $choice, ?string $phone = null): Response
    {
        return DB::transaction(function () use ($classForm, $zaloUserId, $choice, $phone) {
            /** @var ClassForm $locked */
            $locked = ClassForm::query()->whereKey($classForm->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('form');

            if (! $this->canParentChangeVote($locked)) {
                throw new RuntimeException('Form đã đóng hoặc hết hạn, không thể đổi ý.');
            }

            /** @var Response $response */
            $response = Response::query()
                ->where('class_form_id', $locked->id)
                ->where('zalo_user_id', $zaloUserId)
                ->where('channel', 'zalo')
                ->where('status', 'valid')
                ->lockForUpdate()
                ->firstOrFail();

            $response->update([
                'choice' => $choice,
                'phone' => $phone ?: $response->phone,
            ]);

            return $response->refresh();
        });
    }
}
