<?php

namespace App\Services\Ocr;

use App\Models\Form;

/**
 * Heuristic checkbox OCR for Phase 1 paper forms.
 * Calibrated multi-box layout per Form, or binary config fallback.
 */
class PaperCheckboxOcrService
{
    /**
     * @return array{suggestion: string, confidence: float, meta?: array<string, mixed>}
     */
    public function analyze(string $absolutePath, ?array $layout = null): array
    {
        if ($layout !== null && isset($layout['boxes']) && is_array($layout['boxes']) && $layout['boxes'] !== []) {
            return $this->analyzeMultiBox($absolutePath, $layout);
        }

        return $this->analyzeBinaryConfig($absolutePath);
    }

    /**
     * @return array{suggestion: string, confidence: float, meta?: array<string, mixed>}
     */
    public function analyzeForForm(string $absolutePath, Form $form): array
    {
        $layout = $form->resolvedOcrLayout();
        if ($layout !== null) {
            return $this->analyzeMultiBox($absolutePath, $layout);
        }

        $result = $this->analyzeBinaryConfig($absolutePath);
        $choices = $form->allowedChoiceValues();
        if ($result['suggestion'] === 'agree' && isset($choices[0])) {
            $result['suggestion'] = $choices[0];
        } elseif ($result['suggestion'] === 'disagree' && isset($choices[1])) {
            $result['suggestion'] = $choices[1];
        }

        return $result;
    }

    /**
     * @param  array{boxes: list<array{id?: string, x: float, y: float, w: float, h: float, option_value: string}>, min_delta?: float, min_ink?: float}  $layout
     * @return array{suggestion: string, confidence: float, meta?: array<string, mixed>}
     */
    public function analyzeMultiBox(string $absolutePath, array $layout): array
    {
        if (! is_file($absolutePath) || ! extension_loaded('gd')) {
            return ['suggestion' => 'unknown', 'confidence' => 0.0];
        }

        $image = $this->loadImage($absolutePath);
        if ($image === false) {
            return ['suggestion' => 'unknown', 'confidence' => 0.0];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $best = $this->scoreMultiBox($image, $layout);
        $rotationsTried = [0];

        if ($this->shouldTryRotation($width, $height, $best)) {
            foreach ([90, 270] as $deg) {
                $rotated = imagerotate($image, $deg === 90 ? -90 : -270, 0);
                if ($rotated === false) {
                    continue;
                }
                $candidate = $this->scoreMultiBox($rotated, $layout);
                $rotationsTried[] = $deg;
                imagedestroy($rotated);
                if ($this->isBetterRotatedResult($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        imagedestroy($image);
        $best['meta'] = array_merge($best['meta'] ?? [], ['rotations_tried' => $rotationsTried]);

        return $best;
    }

    /**
     * @return array{suggestion: string, confidence: float, meta?: array<string, mixed>}
     */
    public function analyzeBinaryConfig(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! extension_loaded('gd')) {
            return ['suggestion' => 'unknown', 'confidence' => 0.0];
        }

        $image = $this->loadImage($absolutePath);
        if ($image === false) {
            return ['suggestion' => 'unknown', 'confidence' => 0.0];
        }

        $cfg = config('ocr.paper_checkbox');
        $layout = [
            'boxes' => [
                [
                    'option_value' => 'agree',
                    'x' => (float) $cfg['agree']['x'],
                    'y' => (float) $cfg['agree']['y'],
                    'w' => (float) $cfg['agree']['w'],
                    'h' => (float) $cfg['agree']['h'],
                ],
                [
                    'option_value' => 'disagree',
                    'x' => (float) $cfg['disagree']['x'],
                    'y' => (float) $cfg['disagree']['y'],
                    'w' => (float) $cfg['disagree']['w'],
                    'h' => (float) $cfg['disagree']['h'],
                ],
            ],
            'min_delta' => (float) ($cfg['min_delta'] ?? 0.025),
            'min_ink' => (float) ($cfg['min_ink'] ?? 0.035),
        ];

        $width = imagesx($image);
        $height = imagesy($image);
        $best = $this->scoreMultiBox($image, $layout);

        if ($this->shouldTryRotation($width, $height, $best)) {
            foreach ([90, 270] as $deg) {
                $rotated = imagerotate($image, $deg === 90 ? -90 : -270, 0);
                if ($rotated === false) {
                    continue;
                }
                $candidate = $this->scoreMultiBox($rotated, $layout);
                imagedestroy($rotated);
                if ($this->isBetterRotatedResult($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        imagedestroy($image);

        return $best;
    }

    /**
     * @param  array{boxes: list<array{x: float, y: float, w: float, h: float, option_value: string}>, min_delta?: float, min_ink?: float}  $layout
     * @return array{suggestion: string, confidence: float, meta?: array<string, mixed>}
     */
    private function scoreMultiBox($image, array $layout): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minDelta = (float) ($layout['min_delta'] ?? config('ocr.paper_checkbox.min_delta', 0.025));
        $minInk = (float) ($layout['min_ink'] ?? config('ocr.paper_checkbox.min_ink', 0.035));
        $softMinInk = min($minInk, 0.018);

        $inks = [];
        $markInks = [];
        foreach ($layout['boxes'] as $box) {
            $value = (string) ($box['option_value'] ?? '');
            if ($value === '') {
                continue;
            }
            [$regionInk, $markInk] = $this->boxInkScore($image, $width, $height, $box);
            $markInks[$value] = $markInk;
            $inks[$value] = $regionInk;
        }

        if ($inks === []) {
            return ['suggestion' => 'unknown', 'confidence' => 0.0];
        }

        $ranked = $inks;
        arsort($ranked);
        $values = array_keys($ranked);
        $scores = array_values($ranked);
        $best = $values[0];
        $bestInk = $scores[0];
        $secondInk = $scores[1] ?? 0.0;
        $delta = $bestInk - $secondInk;

        $markRanked = $markInks;
        arsort($markRanked);
        $markValues = array_keys($markRanked);
        $markScores = array_values($markRanked);
        $bestMark = $markScores[0] ?? 0.0;
        $secondMark = $markScores[1] ?? 0.0;
        $clearMark = $bestMark >= 0.018 && $secondMark <= max(0.008, $bestMark * 0.4);

        // Thin handwritten X: region ink is close (printed borders), center mark is not.
        if ($clearMark && ($markValues[0] ?? '') !== '') {
            $best = $markValues[0];
            $bestInk = max($bestInk, $inks[$best] ?? 0.0);
            $secondInk = max($secondInk, $secondMark);
            $delta = max($delta, $bestMark - $secondMark);
        }

        $meta = [
            'inks' => $inks,
            'mark_inks' => $markInks,
            'delta' => $delta,
        ];

        $strongContrast = $delta >= $minDelta && $secondInk <= $bestInk * 0.55;
        $hasInk = $bestInk >= $minInk || $clearMark || ($bestInk >= $softMinInk && $strongContrast);

        if (! $hasInk) {
            return [
                'suggestion' => 'unknown',
                'confidence' => min(0.45, 0.15 + $bestInk * 3),
                'meta' => $meta,
            ];
        }

        if (! $strongContrast && ! $clearMark) {
            return [
                'suggestion' => 'unknown',
                'confidence' => min(0.45, 0.2 + $delta * 4),
                'meta' => $meta,
            ];
        }

        return [
            'suggestion' => $best,
            'confidence' => round(min(0.92, 0.55 + $delta * 5), 3),
            'meta' => $meta,
        ];
    }

    /**
     * Only rotate landscape phone photos; portrait shots already match the form layout.
     *
     * @param  array{suggestion: string, confidence: float, meta?: array<string, mixed>}  $upright
     */
    private function shouldTryRotation(int $width, int $height, array $upright): bool
    {
        if ($height >= $width) {
            return false;
        }

        if (($upright['suggestion'] ?? 'unknown') !== 'unknown') {
            return false;
        }

        if (($upright['confidence'] ?? 0) > 0.2) {
            return false;
        }

        $maxInk = 0.0;
        foreach (($upright['meta']['inks'] ?? []) as $ink) {
            $maxInk = max($maxInk, (float) $ink);
        }

        return $maxInk < 0.012;
    }

    /**
     * @param  array{suggestion: string, confidence: float, meta?: array<string, mixed>}  $candidate
     * @param  array{suggestion: string, confidence: float, meta?: array<string, mixed>}  $upright
     */
    private function isBetterRotatedResult(array $candidate, array $upright): bool
    {
        if (($candidate['suggestion'] ?? 'unknown') === 'unknown') {
            return false;
        }

        $uprightMax = 0.0;
        foreach (($upright['meta']['inks'] ?? []) as $ink) {
            $uprightMax = max($uprightMax, (float) $ink);
        }

        $candidateMax = 0.0;
        foreach (($candidate['meta']['inks'] ?? []) as $ink) {
            $candidateMax = max($candidateMax, (float) $ink);
        }

        if ($candidateMax < max(0.035, $uprightMax * 2.5)) {
            return false;
        }

        return ((float) ($candidate['confidence'] ?? 0)) > ((float) ($upright['confidence'] ?? 0));
    }

    /**
     * Sample around calibrated boxes — phone photos often shift a few pixels.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $box
     * @return array{0: float, 1: float}
     */
    private function boxInkScore($image, int $width, int $height, array $box): array
    {
        $padded = $this->padBox($box);
        $isTiny = ((float) $box['w'] < 2.5) || ((float) $box['h'] < 2.0);
        $nudges = $isTiny
            ? [[0.0, 0.0], [-0.9, 0.0], [0.9, 0.0], [0.0, -0.7], [0.0, 0.7], [-0.9, -0.7], [0.9, 0.7]]
            : [[0.0, 0.0]];

        $bestRegion = 0.0;
        $bestMark = 0.0;

        foreach ($nudges as [$dx, $dy]) {
            $candidate = $padded;
            $candidate['x'] = max(0.0, min(100.0 - $candidate['w'], $candidate['x'] + $dx));
            $candidate['y'] = max(0.0, min(100.0 - $candidate['h'], $candidate['y'] + $dy));
            $regionInk = $this->regionInkRatio($image, $width, $height, $candidate);
            $markInk = $this->markInkRatio($image, $width, $height, $candidate);
            $bestRegion = max($bestRegion, max($regionInk, $markInk * 1.35));
            $bestMark = max($bestMark, $markInk);
        }

        return [$bestRegion, $bestMark];
    }

    /**
     * Expand tiny calibrated boxes; less padding on the right (option text sits there).
     *
     * @param  array{x: float, y: float, w: float, h: float}  $box
     * @return array{x: float, y: float, w: float, h: float}
     */
    private function padBox(array $box): array
    {
        $isTiny = ((float) $box['w'] < 2.5) || ((float) $box['h'] < 2.0);
        $minW = $isTiny ? 3.2 : 2.2;
        $minH = $isTiny ? 2.6 : 2.2;
        $padLeft = $isTiny ? 0.65 : 0.45;
        $padRight = 0.15;
        $padVertical = $isTiny ? 0.55 : 0.45;

        $w = max((float) $box['w'] + $padLeft + $padRight, $minW);
        $h = max((float) $box['h'] + ($padVertical * 2), $minH);
        $cx = (float) $box['x'] + ((float) $box['w'] / 2);
        $cy = (float) $box['y'] + ((float) $box['h'] / 2);
        $x = max(0.0, $cx - (($box['w'] / 2) + $padLeft));
        if ($x + $w > 100) {
            $x = max(0.0, 100 - $w);
        }
        $y = max(0.0, $cy - ($h / 2));
        if ($y + $h > 100) {
            $y = max(0.0, 100 - $h);
        }

        return ['x' => $x, 'y' => $y, 'w' => min($w, 100 - $x), 'h' => min($h, 100 - $y)];
    }

    /**
     * @return \GdImage|false
     */
    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if (! $info) {
            return false;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        if ($image === false) {
            return false;
        }

        return $this->applyExifOrientation($path, $image);
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function applyExifOrientation(string $path, $image)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation <= 1) {
            return $image;
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Pen / pencil marks concentrate in the checkbox center (ignore printed border + nearby text).
     *
     * @param  array{x: float, y: float, w: float, h: float}  $box
     */
    private function markInkRatio($image, int $width, int $height, array $box): float
    {
        return $this->sampleInkRatio($image, $width, $height, $box, 0.5, 0.48);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $box
     */
    private function regionInkRatio($image, int $width, int $height, array $box): float
    {
        return $this->sampleInkRatio($image, $width, $height, $box, 1.0, 0.55);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $box
     */
    private function sampleInkRatio($image, int $width, int $height, array $box, float $innerFraction, float $lumaThreshold): float
    {
        $x0 = (int) max(0, floor($width * $box['x'] / 100));
        $y0 = (int) max(0, floor($height * $box['y'] / 100));
        $x1 = (int) min($width - 1, ceil($width * ($box['x'] + $box['w']) / 100));
        $y1 = (int) min($height - 1, ceil($height * ($box['y'] + $box['h']) / 100));

        if ($x1 <= $x0 || $y1 <= $y0) {
            return 0.0;
        }

        $boxW = $x1 - $x0;
        $boxH = $y1 - $y0;
        $marginX = (int) floor($boxW * (1 - $innerFraction) / 2);
        $marginY = (int) floor($boxH * (1 - $innerFraction) / 2);
        $x0 += $marginX;
        $y0 += $marginY;
        $x1 -= $marginX;
        $y1 -= $marginY;

        if ($x1 <= $x0 || $y1 <= $y0) {
            return 0.0;
        }

        $ink = 0;
        $total = 0;
        $step = max(1, (int) floor(min($x1 - $x0, $y1 - $y0) / 24));

        for ($y = $y0; $y <= $y1; $y += $step) {
            for ($x = $x0; $x <= $x1; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                if ($luma < $lumaThreshold) {
                    $ink++;
                }
                $total++;
            }
        }

        return $total > 0 ? $ink / $total : 0.0;
    }
}
