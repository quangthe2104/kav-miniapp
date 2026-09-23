<?php

namespace App\Jobs;

use App\Models\PaperUploadBatch;
use App\Services\Ocr\PaperCheckboxOcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessPaperOcrBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $batchId) {}

    public function handle(PaperCheckboxOcrService $ocr): void
    {
        $batch = PaperUploadBatch::query()->with(['items', 'classForm.form'])->find($this->batchId);
        if (! $batch) {
            return;
        }

        $form = $batch->classForm?->form;
        $agreeValues = $form?->resolvedOptions()['agree_values'] ?? ['agree'];

        $agree = 0;
        $disagree = 0;
        $unknown = 0;

        foreach ($batch->items as $item) {
            $absolute = Storage::disk('local')->path($item->image_path);
            $result = $form
                ? $ocr->analyzeForForm($absolute, $form)
                : $ocr->analyze($absolute);

            $item->update([
                'ocr_suggestion' => $result['suggestion'],
                'ocr_confidence' => $result['confidence'],
            ]);

            $suggestion = (string) $result['suggestion'];
            if ($suggestion === 'unknown' || $suggestion === '') {
                $unknown++;
            } elseif (in_array($suggestion, $agreeValues, true)) {
                $agree++;
            } else {
                $disagree++;
            }
        }

        $batch->update([
            'status' => 'ready',
            'agree_count' => $agree,
            'disagree_count' => $disagree,
            'unknown_count' => $unknown,
        ]);
    }
}
