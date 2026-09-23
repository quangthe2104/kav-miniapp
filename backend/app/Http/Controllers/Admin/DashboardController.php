<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Province;
use App\Models\School;
use App\Models\Ward;
use App\Services\Admin\FormDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FormDashboardService $dashboard): View
    {
        $forms = Form::query()->orderByDesc('id')->get();

        if ($forms->isEmpty()) {
            return view('admin.dashboard', [
                'forms' => $forms,
                'form' => null,
                'filters' => [
                    'form_id' => null,
                    'province_id' => null,
                    'ward_id' => null,
                    'school_id' => null,
                ],
                'provinces' => Province::query()->orderBy('name')->get(['id', 'name', 'code']),
                'wards' => collect(),
                'schools' => collect(),
                'kpis' => null,
                'choice_totals' => ['agree' => 0, 'disagree' => 0],
                'by_channel' => ['zalo' => 0, 'paper' => 0],
                'daily_labels' => [],
                'daily_values' => [],
                'class_rows' => null,
                'recent' => collect(),
            ]);
        }

        $formId = $request->filled('form_id')
            ? $request->integer('form_id')
            : (int) ($request->session()->get('admin.dashboard.form_id') ?: 0);

        if (! $formId || ! $forms->contains('id', $formId)) {
            $default = $forms->firstWhere('status', 'active') ?? $forms->first();
            $formId = (int) $default->id;
        }

        $request->session()->put('admin.dashboard.form_id', $formId);

        $provinceId = $request->filled('province_id') ? $request->integer('province_id') : null;
        $wardId = $request->filled('ward_id') ? $request->integer('ward_id') : null;
        $schoolId = $request->filled('school_id') ? $request->integer('school_id') : null;

        if ($schoolId && ! $wardId) {
            $wardId = School::query()->whereKey($schoolId)->value('ward_id');
        }
        if ($wardId && ! $provinceId) {
            $provinceId = Ward::query()->whereKey($wardId)->value('province_id');
        }

        $data = $dashboard->build($formId, $provinceId, $wardId, $schoolId);

        $wards = $provinceId
            ? Ward::query()->where('province_id', $provinceId)->orderBy('name')->get(['id', 'name', 'code'])
            : collect();
        $schools = $wardId
            ? School::query()->where('ward_id', $wardId)->orderBy('name')->get(['id', 'name', 'external_id'])
            : collect();

        return view('admin.dashboard', [
            'forms' => $forms,
            'form' => $data['form'],
            'filters' => [
                'form_id' => $formId,
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'school_id' => $schoolId,
            ],
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name', 'code']),
            'wards' => $wards,
            'schools' => $schools,
            'kpis' => $data['kpis'],
            'choice_totals' => $data['choice_totals'],
            'by_channel' => $data['by_channel'],
            'daily_labels' => $data['daily_labels'],
            'daily_values' => $data['daily_values'],
            'class_rows' => $data['class_rows'],
            'recent' => $data['recent'],
        ]);
    }
}
