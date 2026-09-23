<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassProfile;
use App\Models\Province;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Ward;
use App\Services\CoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassProfileController extends Controller
{
    public function create(Request $request): View
    {
        $provinces = Province::query()->orderBy('name')->get();

        return view('teacher.profiles.create', compact('provinces'));
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'class_name' => ['required', 'string', 'max:100'],
            'quota' => ['required', 'integer', 'min:1', 'max:80'],
        ]);

        $profile = ClassProfile::query()->create([
            'school_id' => $data['school_id'],
            'class_name' => $data['class_name'],
            'quota' => $data['quota'],
            'created_by_teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        return redirect()->route('teacher.profiles.show', $profile)->with('status', 'Đã tạo lớp. Tiếp theo: lấy link gửi phụ huynh.');
    }

    public function show(Request $request, ClassProfile $profile, CoverageService $coverage): View
    {
        $this->authorizeTeacher($request, $profile);
        $profile->load(['school.ward.province', 'classForms.form']);
        $forms = \App\Models\Form::query()->activeNow()->orderBy('title')->get();

        $formRows = $forms->map(function ($form) use ($profile, $coverage) {
            $cf = $profile->classForms->firstWhere('form_id', $form->id);
            $stats = $cf ? $coverage->stats($cf) : ['coverage' => 0, 'agree' => 0, 'disagree' => 0, 'total' => 0];
            $quota = max(1, (int) $profile->quota);
            $pct = (int) min(100, round(($stats['coverage'] / $quota) * 100));

            return [
                'form' => $form,
                'class_form' => $cf,
                'coverage' => $stats['coverage'],
                'coverage_pct' => $pct,
                'status' => $cf?->status ?? 'none',
            ];
        });

        return view('teacher.profiles.show', compact('profile', 'forms', 'formRows'));
    }

    public function wards(Province $province)
    {
        return Ward::query()
            ->where('province_id', $province->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function schools(Ward $ward)
    {
        return School::query()
            ->where('ward_id', $ward->id)
            ->orderBy('name')
            ->get(['id', 'name', 'external_id']);
    }

    private function authorizeTeacher(Request $request, ClassProfile $profile): void
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        abort_unless($profile->created_by_teacher_id === $teacher->id, 403);
    }
}
