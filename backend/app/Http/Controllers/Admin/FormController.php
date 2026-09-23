<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\AuditLogService;
use App\Services\Ocr\CheckboxDetectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    public function index(): View
    {
        $forms = Form::query()->latest()->paginate(20);

        return view('admin.forms.index', compact('forms'));
    }

    public function create(): View
    {
        return view('admin.forms.create', [
            'resolved' => Form::defaultOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedFormData($request);

        if ($request->hasFile('consent_pdf')) {
            $data['consent_pdf_path'] = $request->file('consent_pdf')->store('consent-forms', 'local');
        }
        unset($data['consent_pdf']);

        $form = Form::query()->create($data);

        if (! empty($data['consent_pdf_path'])) {
            return redirect()
                ->route('admin.forms.edit', $form)
                ->with('status', 'Đã tạo Form. Đang mở cấu hình OCR từ PDF…')
                ->with('ocr_auto', true);
        }

        return redirect()->route('admin.forms.index')->with('status', 'Đã tạo Form.');
    }

    public function edit(Form $form): View
    {
        return view('admin.forms.edit', [
            'form' => $form,
            'resolved' => $form->resolvedOptions(),
        ]);
    }

    public function update(Request $request, Form $form, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validatedFormData($request);

        if ($request->hasFile('consent_pdf')) {
            $old = $form->consent_pdf_path;
            $data['consent_pdf_path'] = $request->file('consent_pdf')->store('consent-forms', 'local');
            if ($old && $old !== $data['consent_pdf_path'] && Storage::disk('local')->exists($old)) {
                Storage::disk('local')->delete($old);
            }
        }
        unset($data['consent_pdf']);

        $layout = $this->validatedOcrLayout($request, $form, $data['options_json'] ?? null);
        if ($layout !== null) {
            $data['ocr_layout_json'] = $layout;
        }

        $previouslyActive = $form->isActive();

        $form->fill($data);
        $form->invalidateOcrLayoutIfOptionsDrift();
        $form->save();

        if (! $form->isActive()) {
            $closed = $form->closeLinkedClassForms();
            if ($closed > 0) {
                $audit->record('form.cascade_close_class_forms', $request->user(), $form, [
                    'class_forms_closed' => $closed,
                    'form_status' => $form->status,
                ], $request);
            }
        } elseif (! $previouslyActive) {
            $reopened = $form->reopenLinkedClassForms();
            if ($reopened > 0) {
                $audit->record('form.cascade_reopen_class_forms', $request->user(), $form, [
                    'class_forms_reopened' => $reopened,
                    'form_status' => $form->status,
                ], $request);
            }
        }

        if ($layout !== null && ! empty($layout['confirmed_at'])) {
            $audit->record('form.ocr_layout.update', $request->user(), $form, [
                'boxes' => count($layout['boxes'] ?? []),
                'confirmed' => true,
            ], $request);
        }

        return redirect()->route('admin.forms.edit', $form)->with('status', 'Đã cập nhật Form.')
            ->with('ocr_auto', $request->hasFile('consent_pdf'));
    }

    public function detectOcr(
        Request $request,
        Form $form,
        CheckboxDetectService $detect,
        AuditLogService $audit,
    ): JsonResponse {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:max_width=4500,max_height=4500'],
            'zone' => ['nullable', 'array'],
            'zone.x' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'zone.y' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'zone.w' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'zone.h' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ]);

        $file = $request->file('image');
        $tmp = $file->store('ocr-calibration/'.$form->id.'/tmp', 'local');
        $absolute = Storage::disk('local')->path($tmp);

        try {
            $zoneHint = isset($data['zone']) && is_array($data['zone']) ? $data['zone'] : null;
            $maxBoxes = max(2, count($form->allowedChoiceValues()));
            if ($zoneHint === null) {
                // Auto: try lower block first, then wider page region.
                $result = $detect->detect($absolute, ['x' => 5.0, 'y' => 55.0, 'w' => 90.0, 'h' => 40.0], $maxBoxes);
                if (count($result['boxes']) < 2) {
                    $result = $detect->detect($absolute, ['x' => 5.0, 'y' => 35.0, 'w' => 90.0, 'h' => 55.0], $maxBoxes);
                }
            } else {
                $result = $detect->detect($absolute, $zoneHint, $maxBoxes);
            }
            $previewPath = $detect->storePreview($absolute, $form->id);
        } finally {
            Storage::disk('local')->delete($tmp);
        }

        $choices = $form->resolvedOptions()['choices'];
        $boxes = $result['boxes'];
        foreach ($boxes as $i => &$box) {
            if (isset($choices[$i])) {
                $box['option_value'] = $choices[$i]['value'];
            }
        }
        unset($box);

        $draft = $form->ocrLayout() ?? [];
        $draft['preview_path'] = $previewPath;
        $draft['zone'] = $result['zone'];
        $draft['boxes'] = $boxes;
        unset($draft['confirmed_at']);
        $form->update(['ocr_layout_json' => $draft]);

        $audit->record('form.ocr_calibrate.detect', $request->user(), $form, [
            'boxes' => count($boxes),
        ], $request);

        $choiceCount = count($choices);
        $boxCount = count($boxes);
        $needsManualZone = $boxCount < 2;

        $warning = null;
        if ($needsManualZone) {
            $warning = 'Chưa nhận diện đủ ô. Hãy kéo một vùng bao các ô chọn trên ảnh, rồi bấm «Nhận diện lại».';
        } elseif ($boxCount !== $choiceCount) {
            $warning = 'Số ô phát hiện ('.$boxCount.') khác số lựa chọn Form ('.$choiceCount.'). Hãy map tay hoặc kéo lại vùng rồi nhận diện lại.';
        }

        return response()->json([
            'zone' => $result['zone'],
            'boxes' => $boxes,
            'preview_path' => $previewPath,
            'preview_url' => route('admin.forms.ocr.preview', $form),
            'choices' => $choices,
            'needs_manual_zone' => $needsManualZone,
            'warning' => $warning,
        ]);
    }

    public function previewOcr(Form $form): StreamedResponse
    {
        $layout = $form->ocrLayout();
        $path = is_array($layout) ? (string) ($layout['preview_path'] ?? '') : '';
        $prefix = 'ocr-calibration/'.$form->id.'/';
        abort_unless(
            $path !== ''
            && str_starts_with(str_replace('\\', '/', $path), $prefix)
            && ! str_contains($path, '..')
            && Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->response($path);
    }

    /**
     * Stream the uploaded consent PDF (inline preview / download).
     */
    public function consentPdf(Form $form): StreamedResponse
    {
        $path = (string) ($form->consent_pdf_path ?? '');
        abort_unless(
            $path !== ''
            && str_starts_with(str_replace('\\', '/', $path), 'consent-forms/')
            && ! str_contains($path, '..')
            && Storage::disk('local')->exists($path),
            404,
            'Chưa có PDF mẫu.'
        );

        $downloadName = 'mau-phieu-'.Str::slug($form->title).'.pdf';

        return Storage::disk('local')->response($path, $downloadName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFormData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', 'in:draft,active,closed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'no_end_date' => ['sometimes', 'boolean'],
            'options_preset' => ['required', Rule::in([
                Form::PRESET_AGREE_DISAGREE,
                Form::PRESET_YES_NO,
                Form::PRESET_CUSTOM,
            ])],
            'custom_choices' => ['nullable', 'array', 'min:2'],
            'custom_choices.*.value' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'custom_choices.*.label' => ['nullable', 'string', 'max:100'],
            'agree_values' => ['nullable', 'array'],
            'agree_values.*' => ['string', 'max:32'],
            'consent_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $noEndDate = $request->boolean('no_end_date');
        $data['no_end_date'] = $noEndDate;
        if ($noEndDate) {
            $data['ends_at'] = null;
        }

        $data['options_json'] = $this->buildOptionsJson(
            $data['options_preset'],
            $data['custom_choices'] ?? [],
            $data['agree_values'] ?? []
        );

        unset($data['options_preset'], $data['custom_choices'], $data['agree_values']);

        return $data;
    }

    /**
     * @param  array{choices?: list<array{value: string}>}|null  $upcomingOptions
     * @return array<string, mixed>|null
     */
    private function validatedOcrLayout(Request $request, Form $form, ?array $upcomingOptions = null): ?array
    {
        if (! $request->has('ocr_layout_json')) {
            return null;
        }

        $raw = $request->input('ocr_layout_json');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                // Empty / missing calibration — leave existing layout
                if (trim($raw) === '' || $raw === 'null') {
                    return null;
                }
                throw ValidationException::withMessages([
                    'ocr_layout_json' => 'JSON cấu hình OCR không hợp lệ.',
                ]);
            }
            $raw = $decoded;
        }
        if (! is_array($raw)) {
            return null;
        }

        $zone = $raw['zone'] ?? null;
        $boxesIn = is_array($raw['boxes'] ?? null) ? $raw['boxes'] : [];
        $hasZone = is_array($zone)
            && isset($zone['x'], $zone['y'], $zone['w'], $zone['h'])
            && (float) $zone['w'] >= 1
            && (float) $zone['h'] >= 1;

        if (! $hasZone && $boxesIn === [] && ! $request->boolean('ocr_layout_confirm')) {
            return null;
        }

        $existing = $form->ocrLayout() ?? [];
        $previewPath = (string) ($existing['preview_path'] ?? $raw['preview_path'] ?? '');
        $prefix = 'ocr-calibration/'.$form->id.'/';
        if ($previewPath !== '' && (! str_starts_with(str_replace('\\', '/', $previewPath), $prefix) || str_contains($previewPath, '..'))) {
            throw ValidationException::withMessages([
                'ocr_layout_json' => 'Đường dẫn preview không hợp lệ.',
            ]);
        }

        if (! $hasZone) {
            throw ValidationException::withMessages(['ocr_layout_json' => 'Thiếu vùng (zone) OCR.']);
        }

        $zoneNorm = [
            'x' => max(0, min(99, (float) $zone['x'])),
            'y' => max(0, min(99, (float) $zone['y'])),
            'w' => max(1, min(100, (float) $zone['w'])),
            'h' => max(1, min(100, (float) $zone['h'])),
        ];

        if (count($boxesIn) > 50) {
            throw ValidationException::withMessages(['ocr_layout_json' => 'Tối đa 50 ô checkbox.']);
        }

        $allowed = isset($upcomingOptions['choices']) && is_array($upcomingOptions['choices'])
            ? array_values(array_filter(array_map(
                fn ($c) => is_array($c) ? trim((string) ($c['value'] ?? '')) : '',
                $upcomingOptions['choices']
            )))
            : $form->allowedChoiceValues();

        $boxes = [];
        foreach ($boxesIn as $i => $box) {
            if (! is_array($box)) {
                continue;
            }
            $boxes[] = [
                'id' => trim((string) ($box['id'] ?? 'b'.($i + 1))) ?: 'b'.($i + 1),
                'x' => max(0, min(100, (float) ($box['x'] ?? 0))),
                'y' => max(0, min(100, (float) ($box['y'] ?? 0))),
                'w' => max(0.5, min(100, (float) ($box['w'] ?? 1))),
                'h' => max(0.5, min(100, (float) ($box['h'] ?? 1))),
                'shape' => in_array(($box['shape'] ?? 'square'), ['square', 'circle'], true)
                    ? (string) $box['shape']
                    : 'square',
                'option_value' => trim((string) ($box['option_value'] ?? '')),
            ];
        }

        $layout = [
            'preview_path' => $previewPath !== '' ? $previewPath : null,
            'zone' => $zoneNorm,
            'boxes' => $boxes,
        ];
        if (isset($raw['min_delta']) || isset($existing['min_delta'])) {
            $layout['min_delta'] = (float) ($raw['min_delta'] ?? $existing['min_delta']);
        }
        if (isset($raw['min_ink']) || isset($existing['min_ink'])) {
            $layout['min_ink'] = (float) ($raw['min_ink'] ?? $existing['min_ink']);
        }

        if ($request->boolean('ocr_layout_confirm')) {
            if (count($boxes) < 2) {
                throw ValidationException::withMessages([
                    'ocr_layout_json' => 'Cần ít nhất 2 ô đã nhận diện để xác nhận OCR.',
                ]);
            }
            $values = [];
            foreach ($boxes as $box) {
                if ($box['option_value'] === '' || ! in_array($box['option_value'], $allowed, true)) {
                    throw ValidationException::withMessages([
                        'ocr_layout_json' => 'Mỗi ô phải map tới một lựa chọn hợp lệ của Form trước khi xác nhận.',
                    ]);
                }
                $values[] = $box['option_value'];
            }
            if (count(array_unique($values)) < 2) {
                throw ValidationException::withMessages([
                    'ocr_layout_json' => 'Cần map ít nhất 2 lựa chọn khác nhau.',
                ]);
            }
            $layout['confirmed_at'] = now()->toIso8601String();
        } else {
            $layout['confirmed_at'] = null;
        }

        return $layout;
    }

    /**
     * @param  list<array{value?: string, label?: string}>  $customChoices
     * @param  list<string>  $agreeValues
     * @return array{preset: string, choices: list<array{value: string, label: string}>, agree_values: list<string>}
     */
    private function buildOptionsJson(string $preset, array $customChoices, array $agreeValues): array
    {
        if ($preset !== Form::PRESET_CUSTOM) {
            return Form::presetOptions($preset);
        }

        $choices = [];
        foreach ($customChoices as $row) {
            $rawValue = trim((string) ($row['value'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $value = $rawValue !== ''
                ? Str::lower(Str::slug($rawValue, '_'))
                : ($label !== '' ? Str::lower(Str::slug($label, '_')) : '');
            if ($value === '' || $label === '') {
                continue;
            }
            if (strlen($value) > 32) {
                $value = substr($value, 0, 32);
            }
            $choices[] = ['value' => $value, 'label' => $label];
        }

        if (count($choices) < 2) {
            throw ValidationException::withMessages([
                'custom_choices' => 'Preset tùy chỉnh cần ít nhất 2 lựa chọn (value slug + label).',
            ]);
        }

        $values = array_column($choices, 'value');
        if (count($values) !== count(array_unique($values))) {
            throw ValidationException::withMessages([
                'custom_choices' => 'Giá trị (value) của các lựa chọn không được trùng.',
            ]);
        }

        $agree = [];
        foreach ($agreeValues as $v) {
            $normalized = Str::lower(Str::slug((string) $v, '_'));
            if ($normalized !== '' && in_array($normalized, $values, true)) {
                $agree[] = $normalized;
            }
        }
        $agree = array_values(array_unique($agree));
        if ($agree === []) {
            throw ValidationException::withMessages([
                'agree_values' => 'Chọn ít nhất một lựa chọn được tính là “đồng ý” (KPI).',
            ]);
        }

        return [
            'preset' => Form::PRESET_CUSTOM,
            'choices' => $choices,
            'agree_values' => $agree,
        ];
    }
}
