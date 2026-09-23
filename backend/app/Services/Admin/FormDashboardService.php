<?php

namespace App\Services\Admin;

use App\Models\ClassForm;
use App\Models\Form;
use App\Models\Response;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FormDashboardService
{
    /**
     * @return array{
     *     form: Form,
     *     agree_values: list<string>,
     *     kpis: array<string, int|float|null>,
     *     by_channel: array{zalo: int, paper: int},
     *     choice_totals: array{agree: int, disagree: int},
     *     daily_labels: list<string>,
     *     daily_values: list<int>,
     *     class_rows: LengthAwarePaginator,
     *     recent: Collection<int, Response>
     * }
     */
    public function build(int $formId, ?int $provinceId = null, ?int $wardId = null, ?int $schoolId = null): array
    {
        $form = Form::query()->findOrFail($formId);
        $agreeValues = $this->agreeValues($form);

        $schoolsTotal = $this->schoolDenominator($provinceId, $wardId, $schoolId);
        $schoolsParticipating = $this->schoolsWithClassForm($formId, $provinceId, $wardId, $schoolId);
        $classFormsTotal = $this->classFormsCount($formId, $provinceId, $wardId, $schoolId);
        $classFormsCompleted = $this->completedClassFormsCount($formId, $provinceId, $wardId, $schoolId);

        $choiceRaw = $this->choiceBreakdown($formId, $provinceId, $wardId, $schoolId);
        $agree = 0;
        $disagree = 0;
        foreach ($choiceRaw as $choice => $total) {
            if (in_array((string) $choice, $agreeValues, true)) {
                $agree += (int) $total;
            } else {
                $disagree += (int) $total;
            }
        }
        $validResponses = $agree + $disagree;

        $byChannel = $this->channelBreakdown($formId, $provinceId, $wardId, $schoolId);
        [$dailyLabels, $dailyValues] = $this->dailySeries($formId, $provinceId, $wardId, $schoolId);

        return [
            'form' => $form,
            'agree_values' => $agreeValues,
            'kpis' => [
                'schools_total' => $schoolsTotal,
                'schools_participating' => $schoolsParticipating,
                'school_participation_pct' => $this->pct($schoolsParticipating, $schoolsTotal),
                'class_forms_total' => $classFormsTotal,
                'class_forms_completed' => $classFormsCompleted,
                'class_completion_pct' => $this->pct($classFormsCompleted, $classFormsTotal),
                'valid_responses' => $validResponses,
                'agree_rate_pct' => $this->pct($agree, $validResponses),
            ],
            'by_channel' => [
                'zalo' => (int) ($byChannel['zalo'] ?? 0),
                'paper' => (int) ($byChannel['paper'] ?? 0),
            ],
            'choice_totals' => [
                'agree' => $agree,
                'disagree' => $disagree,
            ],
            'daily_labels' => $dailyLabels,
            'daily_values' => $dailyValues,
            'class_rows' => $this->classRows($formId, $agreeValues, $provinceId, $wardId, $schoolId),
            'recent' => $this->recentResponses($formId, $provinceId, $wardId, $schoolId),
        ];
    }

    /**
     * @return list<string>
     */
    public function agreeValues(Form $form): array
    {
        return $form->resolvedOptions()['agree_values'];
    }

    public function schoolDenominator(?int $provinceId, ?int $wardId, ?int $schoolId): int
    {
        return (int) School::query()
            ->when($schoolId, fn (Builder $q) => $q->whereKey($schoolId))
            ->when($wardId && ! $schoolId, fn (Builder $q) => $q->where('ward_id', $wardId))
            ->when($provinceId && ! $wardId && ! $schoolId, function (Builder $q) use ($provinceId) {
                $q->whereHas('ward', fn (Builder $w) => $w->where('province_id', $provinceId));
            })
            ->count();
    }

    public function schoolsWithClassForm(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): int
    {
        return (int) ClassForm::query()
            ->where('form_id', $formId)
            ->tap(fn (Builder $q) => $this->applyGeoToClassForms($q, $provinceId, $wardId, $schoolId))
            ->join('class_profiles', 'class_profiles.id', '=', 'class_forms.class_profile_id')
            ->distinct()
            ->count('class_profiles.school_id');
    }

    public function classFormsCount(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): int
    {
        return (int) ClassForm::query()
            ->where('form_id', $formId)
            ->tap(fn (Builder $q) => $this->applyGeoToClassForms($q, $provinceId, $wardId, $schoolId))
            ->count();
    }

    public function completedClassFormsCount(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): int
    {
        return (int) ClassForm::query()
            ->where('form_id', $formId)
            ->tap(fn (Builder $q) => $this->applyGeoToClassForms($q, $provinceId, $wardId, $schoolId))
            ->where(function (Builder $q) {
                $q->whereIn('class_forms.status', ['quota_full', 'closed'])
                    ->orWhereRaw(
                        '(SELECT COALESCE(SUM(r.coverage_weight), 0) FROM responses r WHERE r.class_form_id = class_forms.id AND r.status = ?)
                         >= (SELECT cp.quota FROM class_profiles cp WHERE cp.id = class_forms.class_profile_id)',
                        ['valid']
                    );
            })
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function choiceBreakdown(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): array
    {
        return Response::query()
            ->where('status', 'valid')
            ->whereIn('class_form_id', $this->scopedClassFormIds($formId, $provinceId, $wardId, $schoolId))
            ->selectRaw('choice, COUNT(*) as total')
            ->groupBy('choice')
            ->pluck('total', 'choice')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function channelBreakdown(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): array
    {
        return Response::query()
            ->where('status', 'valid')
            ->whereIn('class_form_id', $this->scopedClassFormIds($formId, $provinceId, $wardId, $schoolId))
            ->selectRaw('channel, COUNT(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array{0: list<string>, 1: list<int>}
     */
    public function dailySeries(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId, int $days = 14): array
    {
        $classFormIds = $this->scopedClassFormIds($formId, $provinceId, $wardId, $schoolId);
        $daily = Response::query()
            ->where('status', 'valid')
            ->whereIn('class_form_id', $classFormIds)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('total', 'd');

        $dayKeys = collect(range($days - 1, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $labels = $dayKeys->map(fn ($d) => date('d/m', strtotime($d)))->values()->all();
        $values = $dayKeys->map(fn ($d) => (int) ($daily[$d] ?? 0))->values()->all();

        return [$labels, $values];
    }

    /**
     * @param  list<string>  $agreeValues
     */
    public function classRows(
        int $formId,
        array $agreeValues,
        ?int $provinceId = null,
        ?int $wardId = null,
        ?int $schoolId = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        $paginator = ClassForm::query()
            ->select('class_forms.*')
            ->with(['classProfile.school', 'classProfile.teacher'])
            ->withSum(
                ['responses as coverage_sum' => fn (Builder $q) => $q->where('status', 'valid')],
                'coverage_weight'
            )
            ->withCount([
                'responses as agree_count' => fn (Builder $q) => $q->where('status', 'valid')->whereIn('choice', $agreeValues),
                'responses as valid_count' => fn (Builder $q) => $q->where('status', 'valid'),
            ])
            ->where('class_forms.form_id', $formId)
            ->tap(fn (Builder $q) => $this->applyGeoToClassForms($q, $provinceId, $wardId, $schoolId))
            ->join('class_profiles', 'class_profiles.id', '=', 'class_forms.class_profile_id')
            ->orderBy('class_profiles.class_name')
            ->orderBy('class_forms.id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function (ClassForm $cf) {
            $quota = (int) ($cf->classProfile?->quota ?? 0);
            $coverage = (int) ($cf->coverage_sum ?? 0);
            $valid = (int) ($cf->valid_count ?? 0);
            $agree = (int) ($cf->agree_count ?? 0);
            $completed = in_array($cf->status, ['quota_full', 'closed'], true)
                || ($quota > 0 && $coverage >= $quota);

            $cf->setAttribute('completion_pct', $quota > 0 ? round(min(100, ($coverage / $quota) * 100), 1) : 0.0);
            $cf->setAttribute('agree_pct', $valid > 0 ? round(($agree / $valid) * 100, 1) : null);
            $cf->setAttribute('is_completed', $completed);

            return $cf;
        });

        return $paginator;
    }

    /**
     * @return Collection<int, Response>
     */
    public function recentResponses(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId, int $limit = 12): Collection
    {
        return Response::query()
            ->with(['classForm.classProfile.school'])
            ->where('status', 'valid')
            ->whereIn('class_form_id', $this->scopedClassFormIds($formId, $provinceId, $wardId, $schoolId))
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function applyGeoToClassForms(Builder $query, ?int $provinceId, ?int $wardId, ?int $schoolId): void
    {
        if (! $provinceId && ! $wardId && ! $schoolId) {
            return;
        }

        $query->whereHas('classProfile.school', function (Builder $q) use ($provinceId, $wardId, $schoolId) {
            if ($schoolId) {
                $q->whereKey($schoolId);
            }
            if ($wardId) {
                $q->where('ward_id', $wardId);
            }
            if ($provinceId) {
                $q->whereHas('ward', fn (Builder $w) => $w->where('province_id', $provinceId));
            }
        });
    }

    /**
     * @return Builder<ClassForm>
     */
    private function scopedClassFormQuery(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId): Builder
    {
        return ClassForm::query()
            ->where('form_id', $formId)
            ->tap(fn (Builder $q) => $this->applyGeoToClassForms($q, $provinceId, $wardId, $schoolId));
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>|Builder
     */
    private function scopedClassFormIds(int $formId, ?int $provinceId, ?int $wardId, ?int $schoolId)
    {
        return $this->scopedClassFormQuery($formId, $provinceId, $wardId, $schoolId)->select('class_forms.id');
    }

    private function pct(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
