<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassForm;
use App\Models\MiniAppUser;
use App\Services\CoverageService;
use Illuminate\View\View;

class ClassFormController extends Controller
{
    public function show(ClassForm $classForm, CoverageService $coverage): View
    {
        $classForm->load(['form', 'classProfile.school.ward.province', 'classProfile.teacher']);

        $stats = $coverage->stats($classForm);
        $coverageWeight = $coverage->currentCoverage($classForm);

        $responses = $classForm->responses()
            ->where('status', 'valid')
            ->latest()
            ->paginate(40)
            ->withQueryString();

        $zaloIds = $responses->getCollection()->pluck('zalo_user_id')->filter()->unique()->values();
        $zaloUsers = $zaloIds->isEmpty()
            ? collect()
            : MiniAppUser::query()
                ->whereIn('zalo_user_id', $zaloIds)
                ->get(['zalo_user_id', 'name', 'phone'])
                ->keyBy('zalo_user_id');

        return view('admin.class-forms.show', [
            'classForm' => $classForm,
            'stats' => $stats,
            'coverage' => $coverageWeight,
            'responses' => $responses,
            'zaloUsers' => $zaloUsers,
        ]);
    }
}
