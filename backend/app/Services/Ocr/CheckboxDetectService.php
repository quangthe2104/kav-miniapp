<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Heuristic checkbox finder (GD): dark square/circle-like frames inside a zone.
 */
class CheckboxDetectService
{
    /**
     * @param  array{x?: float, y?: float, w?: float, h?: float}|null  $zoneHint
     * @return array{
     *     zone: array{x: float, y: float, w: float, h: float},
     *     boxes: list<array{id: string, x: float, y: float, w: float, h: float, shape: string}>
     * }
     */
    public function detect(string $absoluteImagePath, ?array $zoneHint = null, int $maxBoxes = 8): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is required for checkbox detect.');
        }

        $image = $this->loadImage($absoluteImagePath);
        if ($image === false) {
            throw new RuntimeException('Không đọc được ảnh calibration.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $zone = $this->normalizeZone($zoneHint, $width, $height);
        $boxes = $this->findBoxes($image, $width, $height, $zone, $maxBoxes);
        imagedestroy($image);

        return [
            'zone' => $zone,
            'boxes' => $boxes,
        ];
    }

    public function storePreview(string $absoluteImagePath, int $formId): string
    {
        $info = @getimagesize($absoluteImagePath);
        if (! $info) {
            throw new RuntimeException('Ảnh preview không hợp lệ.');
        }

        $ext = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => throw new RuntimeException('Chỉ hỗ trợ JPG/PNG/WebP.'),
        };

        $dir = 'ocr-calibration/'.$formId;
        Storage::disk('local')->makeDirectory($dir);
        $relative = $dir.'/preview.'.$ext;
        $dest = Storage::disk('local')->path($relative);

        if (! @copy($absoluteImagePath, $dest)) {
            throw new RuntimeException('Không lưu được ảnh preview.');
        }

        return $relative;
    }

    /**
     * @param  array{x?: float, y?: float, w?: float, h?: float}|null  $zoneHint
     * @return array{x: float, y: float, w: float, h: float}
     */
    private function normalizeZone(?array $zoneHint, int $width, int $height): array
    {
        if ($zoneHint && isset($zoneHint['x'], $zoneHint['y'], $zoneHint['w'], $zoneHint['h'])) {
            $x = max(0.0, min(99.0, (float) $zoneHint['x']));
            $y = max(0.0, min(99.0, (float) $zoneHint['y']));
            $w = max(1.0, min(100.0 - $x, (float) $zoneHint['w']));
            $h = max(1.0, min(100.0 - $y, (float) $zoneHint['h']));

            return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        }

        // Default: lower half of page (typical choice block).
        return ['x' => 5.0, 'y' => 55.0, 'w' => 90.0, 'h' => 35.0];
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $zone
     * @return list<array{id: string, x: float, y: float, w: float, h: float, shape: string}>
     */
    private function findBoxes($image, int $width, int $height, array $zone, int $maxBoxes): array
    {
        $x0 = (int) max(0, floor($width * $zone['x'] / 100));
        $y0 = (int) max(0, floor($height * $zone['y'] / 100));
        $x1 = (int) min($width - 1, ceil($width * ($zone['x'] + $zone['w']) / 100));
        $y1 = (int) min($height - 1, ceil($height * ($zone['y'] + $zone['h']) / 100));
        $zw = max(1, $x1 - $x0 + 1);
        $zh = max(1, $y1 - $y0 + 1);

        // Binary mask: dark pixels
        $mask = [];
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                $mask[$y][$x] = $luma < 0.52 ? 1 : 0;
            }
        }

        $visited = [];
        $candidates = [];
        $minSide = max(8, (int) floor(min($zw, $zh) * 0.025));
        $maxSide = max($minSide + 4, (int) floor(min($zw, $zh) * 0.22));

        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                if (($mask[$y][$x] ?? 0) !== 1 || ($visited[$y][$x] ?? false)) {
                    continue;
                }
                $comp = $this->floodFill($mask, $visited, $x, $y, $x0, $y0, $x1, $y1);
                if ($comp['count'] < 20) {
                    continue;
                }
                $bw = $comp['maxX'] - $comp['minX'] + 1;
                $bh = $comp['maxY'] - $comp['minY'] + 1;
                if ($bw < $minSide || $bh < $minSide || $bw > $maxSide || $bh > $maxSide) {
                    continue;
                }
                $aspect = $bw / max(1, $bh);
                if ($aspect < 0.55 || $aspect > 1.8) {
                    continue;
                }
                $area = $bw * $bh;
                $fill = $comp['count'] / max(1, $area);
                // Hollow frames ~0.15–0.55; filled ticks can be higher — still accept moderate fill
                if ($fill < 0.08 || $fill > 0.85) {
                    continue;
                }

                $perimeterScore = $this->ringScore($mask, $comp['minX'], $comp['minY'], $comp['maxX'], $comp['maxY']);
                if ($perimeterScore < 0.35) {
                    continue;
                }

                $shape = abs($aspect - 1.0) <= 0.25 ? 'square' : 'circle';
                if ($shape === 'circle' && ($aspect < 0.75 || $aspect > 1.35)) {
                    // elongated — skip
                    continue;
                }

                $candidates[] = [
                    'minX' => $comp['minX'],
                    'minY' => $comp['minY'],
                    'maxX' => $comp['maxX'],
                    'maxY' => $comp['maxY'],
                    'score' => $perimeterScore * (1.0 - abs($aspect - 1.0)),
                    'shape' => $shape,
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Non-max suppression
        $picked = [];
        foreach ($candidates as $c) {
            $overlap = false;
            foreach ($picked as $p) {
                if ($this->iou($c, $p) > 0.35) {
                    $overlap = true;
                    break;
                }
            }
            if (! $overlap) {
                $picked[] = $c;
            }
            if (count($picked) >= $maxBoxes) {
                break;
            }
        }

        // Reading order: top→bottom, left→right
        usort($picked, function ($a, $b) {
            $dy = $a['minY'] - $b['minY'];
            if (abs($dy) > 12) {
                return $dy <=> 0;
            }

            return $a['minX'] <=> $b['minX'];
        });

        $boxes = [];
        foreach ($picked as $i => $c) {
            // Expand slightly so ink sampling covers the tick area
            $padX = max(1, (int) floor(($c['maxX'] - $c['minX']) * 0.15));
            $padY = max(1, (int) floor(($c['maxY'] - $c['minY']) * 0.15));
            $minX = max($x0, $c['minX'] - $padX);
            $minY = max($y0, $c['minY'] - $padY);
            $maxX = min($x1, $c['maxX'] + $padX);
            $maxY = min($y1, $c['maxY'] + $padY);

            $boxes[] = [
                'id' => 'b'.($i + 1),
                'x' => round($minX / $width * 100, 2),
                'y' => round($minY / $height * 100, 2),
                'w' => round(($maxX - $minX + 1) / $width * 100, 2),
                'h' => round(($maxY - $minY + 1) / $height * 100, 2),
                'shape' => $c['shape'],
            ];
        }

        return $boxes;
    }

    /**
     * @param  array<int, array<int, int>>  $mask
     * @param  array<int, array<int, bool>>  $visited
     * @return array{minX: int, minY: int, maxX: int, maxY: int, count: int}
     */
    private function floodFill(array &$mask, array &$visited, int $sx, int $sy, int $x0, int $y0, int $x1, int $y1): array
    {
        $stack = [[$sx, $sy]];
        $visited[$sy][$sx] = true;
        $minX = $maxX = $sx;
        $minY = $maxY = $sy;
        $count = 0;
        $limit = 8000;

        while ($stack !== [] && $count < $limit) {
            [$x, $y] = array_pop($stack);
            $count++;
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < $x0 || $nx > $x1 || $ny < $y0 || $ny > $y1) {
                    continue;
                }
                if (($mask[$ny][$nx] ?? 0) !== 1 || ($visited[$ny][$nx] ?? false)) {
                    continue;
                }
                $visited[$ny][$nx] = true;
                $stack[] = [$nx, $ny];
            }
        }

        return compact('minX', 'minY', 'maxX', 'maxY', 'count');
    }

    /**
     * @param  array<int, array<int, int>>  $mask
     */
    private function ringScore(array $mask, int $minX, int $minY, int $maxX, int $maxY): float
    {
        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        if ($bw < 4 || $bh < 4) {
            return 0.0;
        }
        $border = 0;
        $borderDark = 0;
        $inner = 0;
        $innerDark = 0;
        $inset = max(1, (int) floor(min($bw, $bh) * 0.22));

        for ($y = $minY; $y <= $maxY; $y++) {
            for ($x = $minX; $x <= $maxX; $x++) {
                $onBorder = $x <= $minX + 1 || $x >= $maxX - 1 || $y <= $minY + 1 || $y >= $maxY - 1;
                $inInner = $x >= $minX + $inset && $x <= $maxX - $inset && $y >= $minY + $inset && $y <= $maxY - $inset;
                $dark = ($mask[$y][$x] ?? 0) === 1;
                if ($onBorder) {
                    $border++;
                    if ($dark) {
                        $borderDark++;
                    }
                } elseif ($inInner) {
                    $inner++;
                    if ($dark) {
                        $innerDark++;
                    }
                }
            }
        }

        $borderRatio = $border > 0 ? $borderDark / $border : 0;
        $innerRatio = $inner > 0 ? $innerDark / $inner : 1;

        return max(0.0, $borderRatio - $innerRatio * 0.5);
    }

    /**
     * @param  array{minX: int, minY: int, maxX: int, maxY: int}  $a
     * @param  array{minX: int, minY: int, maxX: int, maxY: int}  $b
     */
    private function iou(array $a, array $b): float
    {
        $ix0 = max($a['minX'], $b['minX']);
        $iy0 = max($a['minY'], $b['minY']);
        $ix1 = min($a['maxX'], $b['maxX']);
        $iy1 = min($a['maxY'], $b['maxY']);
        if ($ix1 < $ix0 || $iy1 < $iy0) {
            return 0.0;
        }
        $inter = ($ix1 - $ix0 + 1) * ($iy1 - $iy0 + 1);
        $areaA = ($a['maxX'] - $a['minX'] + 1) * ($a['maxY'] - $a['minY'] + 1);
        $areaB = ($b['maxX'] - $b['minX'] + 1) * ($b['maxY'] - $b['minY'] + 1);

        return $inter / max(1, $areaA + $areaB - $inter);
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

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }
}
