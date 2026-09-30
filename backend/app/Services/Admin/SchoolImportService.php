<?php

namespace App\Services\Admin;

use App\Imports\SchoolSpreadsheetImport;
use App\Models\Province;
use App\Models\School;
use App\Models\Ward;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class SchoolImportService
{
    public const SESSION_KEY = 'schools_import';

    public const MAPPABLE = [
        'external_id' => 'Mã trường (external_id)',
        'name' => 'Tên trường',
        'ward_code' => 'Mã phường/xã (ward_code)',
        'ward_name' => 'Tên phường/xã (ward_name)',
        'level' => 'Cấp học (level)',
        'province_code' => 'Mã tỉnh (province_code)',
    ];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'external_id' => ['external_id', 'external id', 'id', 'ma truong', 'mã trường', 'ma_truong', 'school_id', 'school id'],
        'name' => ['name', 'ten', 'tên', 'ten truong', 'tên trường', 'ten_truong', 'school_name', 'school name'],
        'ward_code' => ['ward_code', 'ward code', 'ma xa', 'mã xã', 'ma phuong', 'mã phường', 'ma_xa', 'ma_phuong', 'ward id'],
        'ward_name' => ['ward_name', 'ward name', 'ten xa', 'tên xã', 'ten phuong', 'tên phường', 'ten_xa', 'ten_phuong', 'phuong/xa'],
        'level' => ['level', 'cap', 'cấp', 'cap hoc', 'cấp học', 'loai', 'loại'],
        'province_code' => ['province_code', 'province code', 'ma tinh', 'mã tỉnh', 'ma_tinh', 'tinh'],
    ];

    /**
     * @return array{path: string, disk: string, original_name: string, headers: list<string>, preview: list<list<mixed>>, total_rows: int}
     */
    public function storeAndParse(UploadedFile $file, int $userId): array
    {
        $disk = 'local';
        $dir = 'temp/school-imports/'.$userId;
        Storage::disk($disk)->makeDirectory($dir);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $storedName = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs($dir, $storedName, $disk);

        if (! $path) {
            throw new RuntimeException('Không lưu được file upload.');
        }

        $absolute = Storage::disk($disk)->path($path);
        $matrix = $this->readMatrix($absolute);
        if ($matrix === []) {
            Storage::disk($disk)->delete($path);
            throw new RuntimeException('File trống hoặc không đọc được.');
        }

        $headers = $this->normalizeHeaderRow(array_shift($matrix));
        $dataRows = array_values(array_filter($matrix, fn (array $row) => $this->rowHasContent($row)));

        return [
            'path' => $path,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'headers' => $headers,
            'preview' => array_slice($dataRows, 0, 5),
            'total_rows' => count($dataRows),
            'suggested_map' => $this->suggestMapping($headers),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $mapping  field => column index
     * @return array{valid: list<array<string, mixed>>, errors: list<array{row: int, message: string}>, stats: array{total: int, valid: int, invalid: int}}
     */
    public function validate(string $disk, string $path, array $mapping): array
    {
        $this->assertMapping($mapping);

        $absolute = Storage::disk($disk)->path($path);
        $matrix = $this->readMatrix($absolute);
        if ($matrix === []) {
            throw new RuntimeException('Không đọc được file đã upload.');
        }

        array_shift($matrix); // header
        $dataRows = array_values(array_filter($matrix, fn (array $row) => $this->rowHasContent($row)));

        $wardsByCode = Ward::query()->get(['id', 'code', 'name', 'province_id'])->groupBy('code');
        $wardsByName = Ward::query()->get(['id', 'code', 'name', 'province_id']);
        $provincesByCode = Province::query()->pluck('id', 'code');

        $valid = [];
        $errors = [];
        $seenExternal = [];

        foreach ($dataRows as $index => $row) {
            $excelRow = $index + 2; // 1-based + header
            $externalId = $this->cell($row, $mapping, 'external_id');
            $name = $this->cell($row, $mapping, 'name');
            $wardCode = $this->cell($row, $mapping, 'ward_code');
            $wardName = $this->cell($row, $mapping, 'ward_name');
            $level = $this->cell($row, $mapping, 'level');
            $provinceCode = $this->cell($row, $mapping, 'province_code');

            if ($externalId === null || $externalId === '') {
                $errors[] = ['row' => $excelRow, 'message' => 'Thiếu mã trường (external_id).'];

                continue;
            }

            if (is_int($externalId) || is_float($externalId)) {
                $externalId = (string) (int) round((float) $externalId);
            } else {
                $externalId = trim((string) $externalId);
            }

            if ($externalId === '') {
                $errors[] = ['row' => $excelRow, 'message' => 'Thiếu mã trường (external_id).'];

                continue;
            }
            if (isset($seenExternal[$externalId])) {
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "Trùng external_id {$externalId} với dòng {$seenExternal[$externalId]} trong file.",
                ];

                continue;
            }

            if ($name === null || trim((string) $name) === '') {
                $errors[] = ['row' => $excelRow, 'message' => 'Thiếu tên trường.'];

                continue;
            }

            $provinceId = null;
            if ($provinceCode !== null && $provinceCode !== '') {
                $provinceId = $provincesByCode[(string) $provinceCode] ?? null;
                if (! $provinceId) {
                    $errors[] = ['row' => $excelRow, 'message' => "Không tìm thấy tỉnh mã {$provinceCode}."];

                    continue;
                }
            }

            $wardId = null;
            if ($wardCode !== null && $wardCode !== '') {
                $candidates = $wardsByCode->get((string) $wardCode, collect());
                if ($provinceId) {
                    $candidates = $candidates->where('province_id', $provinceId);
                }
                if ($candidates->count() === 1) {
                    $wardId = $candidates->first()->id;
                } elseif ($candidates->isEmpty()) {
                    $errors[] = ['row' => $excelRow, 'message' => "Không tìm thấy phường/xã mã {$wardCode}."];

                    continue;
                } else {
                    $errors[] = ['row' => $excelRow, 'message' => "Mã phường/xã {$wardCode} bị trùng — hãy map thêm province_code."];

                    continue;
                }
            } elseif ($wardName !== null && trim((string) $wardName) !== '') {
                $needle = mb_strtolower(trim((string) $wardName));
                $candidates = $wardsByName->filter(function (Ward $w) use ($needle, $provinceId) {
                    if ($provinceId && (int) $w->province_id !== (int) $provinceId) {
                        return false;
                    }

                    return mb_strtolower($w->name) === $needle;
                });
                if ($candidates->count() === 1) {
                    $wardId = $candidates->first()->id;
                } elseif ($candidates->isEmpty()) {
                    $errors[] = ['row' => $excelRow, 'message' => "Không tìm thấy phường/xã «{$wardName}»."];

                    continue;
                } else {
                    $errors[] = ['row' => $excelRow, 'message' => "Tên phường/xã «{$wardName}» bị trùng — hãy map ward_code hoặc province_code."];

                    continue;
                }
            } else {
                $errors[] = ['row' => $excelRow, 'message' => 'Cần map ward_code hoặc ward_name.'];

                continue;
            }

            $seenExternal[$externalId] = $excelRow;
            $valid[] = [
                'external_id' => $externalId,
                // Sheets exported from web pages sometimes carry HTML entities ("TH &amp; THCS").
                'name' => trim(html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'ward_id' => $wardId,
                'level' => $level !== null && $level !== '' ? trim((string) $level) : null,
                'row' => $excelRow,
            ];
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'stats' => [
                'total' => count($dataRows),
                'valid' => count($valid),
                'invalid' => count($errors),
            ],
        ];
    }

    /**
     * @param  list<array{external_id: string, name: string, ward_id: int, level: ?string}>  $validRows
     * @return array{added: int, updated: int}
     */
    public function commit(array $validRows): array
    {
        $added = 0;
        $updated = 0;

        DB::transaction(function () use ($validRows, &$added, &$updated) {
            foreach ($validRows as $row) {
                $school = School::query()->where('external_id', $row['external_id'])->first();
                if ($school) {
                    $school->update([
                        'ward_id' => $row['ward_id'],
                        'name' => $row['name'],
                        'level' => $row['level'],
                    ]);
                    $updated++;
                } else {
                    School::query()->create([
                        'external_id' => $row['external_id'],
                        'ward_id' => $row['ward_id'],
                        'name' => $row['name'],
                        'level' => $row['level'],
                    ]);
                    $added++;
                }
            }
        });

        return compact('added', 'updated');
    }

    public function forgetFile(?string $disk, ?string $path): void
    {
        if ($disk && $path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     */
    public function assertMapping(array $mapping): void
    {
        if (! isset($mapping['external_id']) || $mapping['external_id'] === '' || $mapping['external_id'] === null) {
            throw new RuntimeException('Bắt buộc map cột external_id.');
        }
        if (! isset($mapping['name']) || $mapping['name'] === '' || $mapping['name'] === null) {
            throw new RuntimeException('Bắt buộc map cột name.');
        }
        $hasWard = (isset($mapping['ward_code']) && $mapping['ward_code'] !== '' && $mapping['ward_code'] !== null)
            || (isset($mapping['ward_name']) && $mapping['ward_name'] !== '' && $mapping['ward_name'] !== null);
        if (! $hasWard) {
            throw new RuntimeException('Cần map ward_code hoặc ward_name.');
        }
    }

    /**
     * @return list<list<mixed>>
     */
    private function readMatrix(string $absolutePath): array
    {
        $sheets = Excel::toArray(new SchoolSpreadsheetImport, $absolutePath);

        return array_map(
            fn (array $row) => array_values($row),
            $sheets[0] ?? []
        );
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return list<string>
     */
    private function normalizeHeaderRow(array $headerRow): array
    {
        $headers = [];
        foreach ($headerRow as $i => $cell) {
            $label = trim((string) ($cell ?? ''));
            if ($i === 0) {
                $label = preg_replace('/^\xEF\xBB\xBF/u', '', $label) ?? $label;
                $label = ltrim($label, "\xEF\xBB\xBF");
            }
            $headers[] = $label !== '' ? $label : 'Cột '.($i + 1);
        }

        return $headers;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function rowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int|null>
     */
    private function suggestMapping(array $headers): array
    {
        $suggested = array_fill_keys(array_keys(self::MAPPABLE), null);
        $normalized = [];
        foreach ($headers as $i => $header) {
            $normalized[$i] = $this->normalizeHeaderKey($header);
        }

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($normalized as $i => $key) {
                if (in_array($key, $aliases, true)) {
                    $suggested[$field] = $i;
                    break;
                }
            }
        }

        return $suggested;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $key = mb_strtolower(trim($header));
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return $key;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|string|null>  $mapping
     */
    private function cell(array $row, array $mapping, string $field): mixed
    {
        if (! array_key_exists($field, $mapping) || $mapping[$field] === '' || $mapping[$field] === null) {
            return null;
        }
        $index = (int) $mapping[$field];

        return $row[$index] ?? null;
    }
}
