<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    public const PRESET_AGREE_DISAGREE = 'agree_disagree';

    public const PRESET_YES_NO = 'yes_no';

    public const PRESET_CUSTOM = 'custom';

    protected $fillable = [
        'title',
        'content',
        'starts_at',
        'ends_at',
        'no_end_date',
        'status',
        'consent_pdf_path',
        'scope_json',
        'options_json',
        'ocr_layout_json',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'no_end_date' => 'boolean',
            'scope_json' => 'array',
            'options_json' => 'array',
            'ocr_layout_json' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ocrLayout(): ?array
    {
        $layout = $this->ocr_layout_json;

        return is_array($layout) ? $layout : null;
    }

    /**
     * True when admin confirmed calibration and boxes map to current choices.
     */
    public function hasCalibratedOcrLayout(): bool
    {
        $layout = $this->ocrLayout();
        if ($layout === null || empty($layout['confirmed_at'])) {
            return false;
        }

        return $this->resolvedOcrLayout() !== null;
    }

    /**
     * Layout usable by multi-box OCR, or null → binary config fallback.
     * Uses Form boxes when ≥2 options are mapped (confirm preferred, not required).
     *
     * @return array{
     *     boxes: list<array{id: string, x: float, y: float, w: float, h: float, option_value: string, shape?: string}>,
     *     min_delta: float,
     *     min_ink: float
     * }|null
     */
    public function resolvedOcrLayout(): ?array
    {
        $layout = $this->ocrLayout();
        if ($layout === null) {
            return null;
        }

        $boxes = $layout['boxes'] ?? null;
        if (! is_array($boxes) || $boxes === []) {
            return null;
        }

        $allowed = $this->allowedChoiceValues();
        $normalized = [];
        $values = [];

        foreach ($boxes as $i => $box) {
            if (! is_array($box)) {
                return null;
            }
            $value = trim((string) ($box['option_value'] ?? ''));
            if ($value === '' || ! in_array($value, $allowed, true)) {
                return null;
            }
            $x = (float) ($box['x'] ?? -1);
            $y = (float) ($box['y'] ?? -1);
            $w = (float) ($box['w'] ?? -1);
            $h = (float) ($box['h'] ?? -1);
            if ($w <= 0 || $h <= 0 || $x < 0 || $y < 0 || $x + $w > 100.5 || $y + $h > 100.5) {
                return null;
            }
            $id = trim((string) ($box['id'] ?? 'b'.($i + 1)));
            $shape = in_array(($box['shape'] ?? 'square'), ['square', 'circle'], true)
                ? (string) $box['shape']
                : 'square';
            $normalized[] = [
                'id' => $id !== '' ? $id : 'b'.($i + 1),
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'shape' => $shape,
                'option_value' => $value,
            ];
            $values[] = $value;
        }

        if (count(array_unique($values)) < 2) {
            return null;
        }

        $cfg = config('ocr.paper_checkbox');

        return [
            'boxes' => $normalized,
            'min_delta' => (float) ($layout['min_delta'] ?? $cfg['min_delta'] ?? 0.025),
            'min_ink' => (float) ($layout['min_ink'] ?? $cfg['min_ink'] ?? 0.035),
        ];
    }

    /**
     * Clear confirmation when choice values no longer match mapped boxes.
     */
    public function invalidateOcrLayoutIfOptionsDrift(): void
    {
        $layout = $this->ocrLayout();
        if ($layout === null || empty($layout['confirmed_at'])) {
            return;
        }

        $allowed = $this->allowedChoiceValues();
        $boxes = is_array($layout['boxes'] ?? null) ? $layout['boxes'] : [];
        foreach ($boxes as $box) {
            $value = trim((string) (is_array($box) ? ($box['option_value'] ?? '') : ''));
            if ($value === '' || ! in_array($value, $allowed, true)) {
                $layout['confirmed_at'] = null;
                $this->ocr_layout_json = $layout;

                return;
            }
        }
    }

    /**
     * Normalized vote options (backward-compat: null options_json → binary Đồng ý/Không).
     *
     * @return array{
     *     preset: string,
     *     choices: list<array{value: string, label: string}>,
     *     agree_values: list<string>
     * }
     */
    public function resolvedOptions(): array
    {
        $stored = $this->options_json;
        if (is_array($stored) && isset($stored['choices']) && is_array($stored['choices']) && $stored['choices'] !== []) {
            $choices = [];
            foreach ($stored['choices'] as $choice) {
                if (! is_array($choice)) {
                    continue;
                }
                $value = trim((string) ($choice['value'] ?? ''));
                $label = trim((string) ($choice['label'] ?? $value));
                if ($value === '') {
                    continue;
                }
                $choices[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }

            if ($choices !== []) {
                $agreeValues = [];
                if (isset($stored['agree_values']) && is_array($stored['agree_values'])) {
                    $allowed = array_column($choices, 'value');
                    foreach ($stored['agree_values'] as $v) {
                        $v = (string) $v;
                        if (in_array($v, $allowed, true)) {
                            $agreeValues[] = $v;
                        }
                    }
                }
                if ($agreeValues === []) {
                    $first = $choices[0]['value'];
                    if (in_array($first, ['agree', 'yes'], true)) {
                        $agreeValues = [$first];
                    } else {
                        $agreeValues = [$first];
                    }
                }

                return [
                    'preset' => (string) ($stored['preset'] ?? self::PRESET_CUSTOM),
                    'choices' => $choices,
                    'agree_values' => array_values(array_unique($agreeValues)),
                ];
            }
        }

        return self::defaultOptions();
    }

    /**
     * @return list<string>
     */
    public function allowedChoiceValues(): array
    {
        return array_column($this->resolvedOptions()['choices'], 'value');
    }

    /**
     * @return array{
     *     preset: string,
     *     choices: list<array{value: string, label: string}>,
     *     agree_values: list<string>
     * }
     */
    public static function defaultOptions(): array
    {
        return [
            'preset' => self::PRESET_AGREE_DISAGREE,
            'choices' => [
                ['value' => 'agree', 'label' => 'Đồng ý'],
                ['value' => 'disagree', 'label' => 'Không đồng ý'],
            ],
            'agree_values' => ['agree'],
        ];
    }

    /**
     * @return array{
     *     preset: string,
     *     choices: list<array{value: string, label: string}>,
     *     agree_values: list<string>
     * }
     */
    public static function presetOptions(string $preset): array
    {
        return match ($preset) {
            self::PRESET_YES_NO => [
                'preset' => self::PRESET_YES_NO,
                'choices' => [
                    ['value' => 'yes', 'label' => 'Có'],
                    ['value' => 'no', 'label' => 'Không'],
                ],
                'agree_values' => ['yes'],
            ],
            self::PRESET_CUSTOM => [
                'preset' => self::PRESET_CUSTOM,
                'choices' => [],
                'agree_values' => [],
            ],
            default => self::defaultOptions(),
        };
    }

    /**
     * Chart / KPI colors: agree KPI = green, disagree = red, other choices = palette.
     *
     * @param  list<string>  $choiceValues  Ordered choice values (without "remaining")
     * @return list<string> Hex colors aligned with $choiceValues
     */
    public function choiceChartColors(array $choiceValues): array
    {
        $agreeValues = $this->resolvedOptions()['agree_values'] ?? [];
        $palette = ['#0a2a66', '#f59e0b', '#6366f1', '#0ea5e9', '#8b5cf6', '#64748b'];
        $paletteIdx = 0;
        $colors = [];

        foreach ($choiceValues as $value) {
            $value = (string) $value;
            if (in_array($value, $agreeValues, true) || in_array($value, ['agree', 'yes'], true)) {
                $colors[] = '#14bf96';
            } elseif (in_array($value, ['disagree', 'no'], true)) {
                $colors[] = '#b42318';
            } else {
                $colors[] = $palette[$paletteIdx % count($palette)];
                $paletteIdx++;
            }
        }

        return $colors;
    }

    public static function remainingChartColor(): string
    {
        return '#cbd5e1';
    }

    public function classForms(): HasMany
    {
        return $this->hasMany(ClassForm::class);
    }

    /**
     * Form đang mở cho PH + GV: status active và trong khoảng ngày.
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->where('no_end_date', true)
                    ->orWhereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    public function closeLinkedClassForms(): int
    {
        return $this->classForms()->where('status', '!=', 'closed')->update(['status' => 'closed']);
    }

    /**
     * Admin mở lại Form: mở lại các class_form đã bị cascade đóng.
     */
    public function reopenLinkedClassForms(): int
    {
        return $this->classForms()->where('status', 'closed')->update(['status' => 'open']);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if (! $this->no_end_date && $this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Nhãn tóm tắt OCR: lựa chọn KPI / lựa chọn còn lại / không đọc được.
     *
     * @return array{agree_label: string, other_label: string, unknown_label: string}
     */
    public static function ocrSummaryLabels(?self $form): array
    {
        $resolved = $form?->resolvedOptions() ?? self::defaultOptions();
        $choices = $resolved['choices'];
        $agreeValues = $resolved['agree_values'];
        $agreeLabel = collect($choices)->first(fn ($c) => in_array($c['value'], $agreeValues, true))['label'] ?? 'Đồng ý';
        $others = collect($choices)
            ->reject(fn ($c) => in_array($c['value'], $agreeValues, true))
            ->values();
        $otherLabel = $others->count() === 1 ? (string) $others[0]['label'] : 'Khác';

        return [
            'agree_label' => $agreeLabel,
            'other_label' => $otherLabel,
            'unknown_label' => 'Không rõ',
        ];
    }

    public function choiceLabel(string $value): string
    {
        foreach ($this->resolvedOptions()['choices'] as $choice) {
            if ($choice['value'] === $value) {
                return $choice['label'];
            }
        }

        return match ($value) {
            'agree', 'yes' => 'Đồng ý',
            'disagree', 'no' => 'Không đồng ý',
            default => $value,
        };
    }
}
