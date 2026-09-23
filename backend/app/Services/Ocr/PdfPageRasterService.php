<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Optional server-side PDF → PNG (Imagick + Ghostscript).
 * Admin UI primarily uses client pdf.js when GS is unavailable.
 */
class PdfPageRasterService
{
    public function isAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }

    /**
     * Rasterize first page of a PDF on local disk to PNG under ocr-calibration/{formId}/.
     */
    public function rasterizeStoredPdf(string $relativePdfPath, int $formId, int $dpi = 140): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Imagick không khả dụng.');
        }

        $absolute = Storage::disk('local')->path($relativePdfPath);
        if (! is_file($absolute)) {
            throw new RuntimeException('Không tìm thấy file PDF.');
        }

        $dir = 'ocr-calibration/'.$formId;
        Storage::disk('local')->makeDirectory($dir);
        $relative = $dir.'/preview.png';
        $dest = Storage::disk('local')->path($relative);

        try {
            $image = new \Imagick;
            $image->setResolution($dpi, $dpi);
            $image->readImage($absolute.'[0]');
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $image->setImageFormat('png');
            $image->writeImage($dest);
            $image->clear();
            $image->destroy();
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Không chuyển PDF sang ảnh trên server (cần Ghostscript). Dùng nhận diện từ trình duyệt. Chi tiết: '.$e->getMessage(),
                0,
                $e
            );
        }

        return $relative;
    }
}
