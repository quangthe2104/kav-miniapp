<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassForm;
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

        return view('admin.class-forms.show', [
            'classForm' => $classForm,
            'stats' => $stats,
            'coverage' => $coverageWeight,
            'responses' => $responses,
        ]);
    }
}
