<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\School;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CatalogController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.schools.index');
    }

    public function provinces(): JsonResponse
    {
        return response()->json(
            Province::query()->orderBy('name')->get(['id', 'name', 'code'])
        );
    }

    public function wards(Province $province): JsonResponse
    {
        return response()->json(
            Ward::query()
                ->where('province_id', $province->id)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
        );
    }

    public function schools(Ward $ward): JsonResponse
    {
        return response()->json(
            School::query()
                ->where('ward_id', $ward->id)
                ->orderBy('name')
                ->get(['id', 'name', 'external_id'])
        );
    }
}
