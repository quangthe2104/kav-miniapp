<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\School;
use App\Models\Ward;
use App\Services\Admin\SchoolImportService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $provinceId = $request->filled('province_id') ? $request->integer('province_id') : null;
        $wardId = $request->filled('ward_id') ? $request->integer('ward_id') : null;
        $q = trim((string) $request->query('q', ''));

        if ($wardId && ! $provinceId) {
            $provinceId = Ward::query()->whereKey($wardId)->value('province_id');
        }

        $provinces = Province::query()->orderBy('name')->get(['id', 'name', 'code']);
        $wards = $provinceId
            ? Ward::query()->where('province_id', $provinceId)->orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        $schools = School::query()
            ->with(['ward.province'])
            ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
            ->when($provinceId && ! $wardId, function ($query) use ($provinceId) {
                $query->whereHas('ward', fn ($w) => $w->where('province_id', $provinceId));
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('external_id', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $totalInScope = School::query()
            ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
            ->when($provinceId && ! $wardId, function ($query) use ($provinceId) {
                $query->whereHas('ward', fn ($w) => $w->where('province_id', $provinceId));
            })
            ->count();

        return view('admin.schools.index', [
            'schools' => $schools,
            'provinces' => $provinces,
            'wards' => $wards,
            'filters' => [
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'q' => $q,
            ],
            'totalInScope' => $totalInScope,
            'totalSystem' => School::query()->count(),
        ]);
    }

    public function importCreate(SchoolImportService $imports): View|RedirectResponse
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (is_array($state) && ($state['step'] ?? null) === 'map') {
            return redirect()->route('admin.schools.import.map');
        }
        if (is_array($state) && ($state['step'] ?? null) === 'confirm') {
            return redirect()->route('admin.schools.import.confirm');
        }

        return view('admin.schools.import-upload');
    }

    public function importUpload(Request $request, SchoolImportService $imports): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Chọn file Excel/CSV để import.',
            'file.mimes' => 'Chỉ chấp nhận .xlsx, .xls hoặc .csv.',
        ]);

        $this->clearImportSession($imports);

        try {
            $parsed = $imports->storeAndParse($request->file('file'), (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        session([SchoolImportService::SESSION_KEY => [
            'step' => 'map',
            'path' => $parsed['path'],
            'disk' => $parsed['disk'],
            'original_name' => $parsed['original_name'],
            'headers' => $parsed['headers'],
            'preview' => $parsed['preview'],
            'total_rows' => $parsed['total_rows'],
            'suggested_map' => $parsed['suggested_map'],
        ]]);

        return redirect()->route('admin.schools.import.map');
    }

    public function importMap(SchoolImportService $imports): View|RedirectResponse
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (! is_array($state) || empty($state['path'])) {
            return redirect()
                ->route('admin.schools.import.create')
                ->withErrors(['file' => 'Phiên import hết hạn. Upload lại file.']);
        }

        return view('admin.schools.import-map', [
            'headers' => $state['headers'],
            'preview' => $state['preview'],
            'totalRows' => $state['total_rows'],
            'originalName' => $state['original_name'],
            'mapping' => $state['mapping'] ?? $state['suggested_map'],
            'fields' => SchoolImportService::MAPPABLE,
        ]);
    }

    public function importValidate(Request $request, SchoolImportService $imports): RedirectResponse
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (! is_array($state) || empty($state['path'])) {
            return redirect()
                ->route('admin.schools.import.create')
                ->withErrors(['file' => 'Phiên import hết hạn. Upload lại file.']);
        }

        $mapping = [];
        foreach (array_keys(SchoolImportService::MAPPABLE) as $field) {
            $value = $request->input("map.{$field}");
            $mapping[$field] = ($value === null || $value === '') ? null : (int) $value;
        }

        try {
            $imports->assertMapping($mapping);
            $result = $imports->validate($state['disk'], $state['path'], $mapping);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.schools.import.map')
                ->withErrors(['map' => $e->getMessage()])
                ->withInput();
        }

        session([SchoolImportService::SESSION_KEY => array_merge($state, [
            'step' => 'confirm',
            'mapping' => $mapping,
            'errors' => array_slice($result['errors'], 0, 100),
            'valid_sample' => array_slice($result['valid'], 0, 10),
            'stats' => $result['stats'],
        ])]);

        return redirect()->route('admin.schools.import.confirm');
    }

    public function importConfirm(SchoolImportService $imports): View|RedirectResponse
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (! is_array($state) || ($state['step'] ?? null) !== 'confirm') {
            return redirect()->route('admin.schools.import.create');
        }

        return view('admin.schools.import-confirm', [
            'originalName' => $state['original_name'],
            'stats' => $state['stats'],
            'errors' => $state['errors'] ?? [],
            'validSample' => $state['valid_sample'] ?? [],
            'fields' => SchoolImportService::MAPPABLE,
            'mapping' => $state['mapping'] ?? [],
            'headers' => $state['headers'] ?? [],
        ]);
    }

    public function importCommit(Request $request, SchoolImportService $imports, AuditLogService $audit): RedirectResponse
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (! is_array($state) || ($state['step'] ?? null) !== 'confirm') {
            return redirect()->route('admin.schools.import.create');
        }

        $maxErrors = 100;
        $invalid = (int) ($state['stats']['invalid'] ?? 0);
        if ($invalid > $maxErrors) {
            return redirect()
                ->route('admin.schools.import.confirm')
                ->withErrors([
                    'commit' => "Quá nhiều lỗi ({$invalid} > {$maxErrors}). Sửa file hoặc map cột rồi validate lại.",
                ]);
        }

        try {
            $resultSet = $imports->validate($state['disk'], $state['path'], $state['mapping'] ?? []);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.schools.import.map')
                ->withErrors(['map' => $e->getMessage()]);
        }

        $valid = $resultSet['valid'];
        if ($valid === []) {
            return redirect()
                ->route('admin.schools.import.confirm')
                ->withErrors(['commit' => 'Không có dòng hợp lệ để import.']);
        }

        $result = $imports->commit($valid);

        $audit->record('admin.schools.import', $request->user(), null, [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'filename' => $state['original_name'] ?? null,
            'total_rows' => $resultSet['stats']['total'] ?? count($valid),
            'invalid_rows' => $resultSet['stats']['invalid'] ?? $invalid,
        ], $request);

        $this->clearImportSession($imports);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', sprintf(
                'Đã import danh sách trường: %d thêm mới, %d cập nhật.',
                $result['added'],
                $result['updated']
            ));
    }

    public function importCancel(SchoolImportService $imports): RedirectResponse
    {
        $this->clearImportSession($imports);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', 'Đã hủy phiên import.');
    }

    private function clearImportSession(SchoolImportService $imports): void
    {
        $state = session(SchoolImportService::SESSION_KEY);
        if (is_array($state)) {
            $imports->forgetFile($state['disk'] ?? null, $state['path'] ?? null);
        }
        session()->forget(SchoolImportService::SESSION_KEY);
    }
}
