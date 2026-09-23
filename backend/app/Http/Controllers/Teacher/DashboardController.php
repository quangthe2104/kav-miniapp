<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassForm;
use App\Models\ClassProfile;
use App\Models\Form;
use App\Models\Teacher;
use App\Services\ClassFormLinkService;
use App\Services\CoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CoverageService $coverage,
        ClassFormLinkService $links,
    ): View|RedirectResponse {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');

        $profiles = ClassProfile::query()
            ->with(['school.ward.province'])
            ->where('created_by_teacher_id', $teacher->id)
            ->orderBy('class_name')
            ->get();

        $isNewTeacher = $profiles->isEmpty();
        $profileId = (int) $request->query('profile_id', 0);

        $selected = $profiles->firstWhere('id', $profileId) ?? $profiles->first();

        $formRows = collect();
        if ($selected) {
            $selected->load(['classForms.form']);
            $linkedByForm = $selected->classForms->keyBy('form_id');

            $activeForms = Form::query()
                ->activeNow()
                ->orderBy('title')
                ->get();

            $rows = collect();

            foreach ($activeForms as $form) {
                $cf = $linkedByForm->get($form->id);
                if ($cf instanceof ClassForm) {
                    $rows->push($this->buildFormRow($cf, $selected, $coverage, $request, $links));
                } else {
                    $rows->push([
                        'class_form' => null,
                        'form' => $form,
                        'title' => $form->title,
                        'status' => 'none',
                        'status_label' => 'Chưa mở link',
                        'coverage' => 0,
                        'coverage_pct' => 0,
                        'quota' => (int) $selected->quota,
                        'choice_counts' => collect($form->resolvedOptions()['choices'])->map(fn ($c) => [
                            'value' => $c['value'],
                            'label' => $c['label'],
                            'count' => 0,
                        ])->all(),
                        'choice_compact' => collect($form->resolvedOptions()['choices'])
                            ->map(fn ($c) => '0')
                            ->implode(' - '),
                        'choice_tooltip' => collect($form->resolvedOptions()['choices'])
                            ->map(fn ($c) => $c['label'].': 0')
                            ->implode("\n"),
                        'vote_url' => null,
                        'has_template' => filled($form->consent_pdf_path)
                            && \Illuminate\Support\Facades\Storage::disk('local')->exists((string) $form->consent_pdf_path),
                        'detail_url' => null,
                        'ensure_url' => route('teacher.profiles.forms.ensure', [$selected, $form], false),
                    ]);
                }
            }

            $formRows = $rows->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        if (
            ! $request->boolean('list')
            && $profiles->count() === 1
            && $formRows->count() === 1
            && $selected
        ) {
            $row = $formRows->first();
            $cf = $row['class_form'] ?? null;
            $form = $row['form'] ?? null;
            if ($form instanceof Form) {
                $cf = $links->ensure($selected, $form)['class_form'];
            }
            if ($cf instanceof ClassForm) {
                return redirect()->route('teacher.class-forms.show', $cf);
            }
        }

        return view('teacher.dashboard', [
            'teacher' => $teacher,
            'profiles' => $profiles,
            'selected' => $selected,
            'formRows' => $formRows,
            'isNewTeacher' => $isNewTeacher,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormRow(
        ClassForm $cf,
        ClassProfile $profile,
        CoverageService $coverage,
        Request $request,
        ClassFormLinkService $links,
    ): array {
        $form = $cf->form;
        $stats = $coverage->stats($cf);
        $quota = max(1, (int) $profile->quota);
        $pct = (int) min(100, round(($stats['coverage'] / $quota) * 100));

        $plain = filled($cf->invite_token)
            ? (string) $cf->invite_token
            : null;
        $voteUrl = $plain ? $links->voteUrl($plain) : null;

        $compact = collect($stats['choice_counts'])->map(fn ($c) => (string) $c['count'])->implode(' - ');
        $tooltip = collect($stats['choice_counts'])
            ->map(fn ($c) => $c['label'].': '.$c['count'])
            ->implode("\n");

        $statusLabel = match ($cf->status) {
            'open' => 'Đang mở',
            'quota_full' => 'Đủ sĩ số',
            'closed' => 'Đã đóng',
            default => $cf->status,
        };

        return [
            'class_form' => $cf,
            'form' => $form,
            'title' => $form?->title ?? 'Form #'.$cf->form_id,
            'status' => $cf->status,
            'status_label' => $statusLabel,
            'coverage' => $stats['coverage'],
            'coverage_pct' => $pct,
            'quota' => (int) $profile->quota,
            'choice_counts' => $stats['choice_counts'],
            'choice_compact' => $compact !== '' ? $compact : '0',
            'choice_tooltip' => $tooltip !== '' ? $tooltip : 'Chưa có phiếu',
            'vote_url' => $voteUrl,
            'has_template' => filled($form?->consent_pdf_path)
                && \Illuminate\Support\Facades\Storage::disk('local')->exists((string) $form->consent_pdf_path),
            'detail_url' => $cf->isVoteOpen()
                ? route('teacher.class-forms.show', $cf, false)
                : null,
            'ensure_url' => $form
                ? route('teacher.profiles.forms.ensure', [$profile, $form], false)
                : null,
        ];
    }
}
