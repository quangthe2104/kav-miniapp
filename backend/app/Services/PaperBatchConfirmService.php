<?php

namespace App\Services;

use App\Models\ClassFormNote;
use App\Models\PaperUploadBatch;
use App\Models\PaperUploadItem;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaperBatchConfirmService
{
    public function __construct(private CoverageService $coverage) {}

    /**
     * @param  list<array{id: int, choice: string}>  $items
     * @return array{confirmed: int, skipped: int}
     */
    public function confirm(PaperUploadBatch $batch, Teacher $teacher, array $items): array
    {
        $confirmed = 0;
        $skipped = 0;
        /** @var list<PaperUploadItem> $confirmedItems */
        $confirmedItems = [];

        DB::transaction(function () use ($items, $batch, $teacher, &$confirmed, &$skipped, &$confirmedItems) {

            foreach ($items as $row) {
                /** @var PaperUploadItem $item */
                $item = PaperUploadItem::query()
                    ->where('paper_upload_batch_id', $batch->id)
                    ->whereKey($row['id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($item->status === 'confirmed') {
                    continue;
                }

                if ($row['choice'] === 'skip') {
                    $item->update(['status' => 'skipped', 'confirmed_choice' => null]);
                    $skipped++;
                    continue;
                }

                $response = $this->coverage->createResponseAtomic($batch->classForm, [
                    'channel' => 'paper',
                    'choice' => $row['choice'],
                    'image_path' => $item->image_path,
                    'ocr_suggestion' => $item->ocr_suggestion,
                    'ocr_confidence' => $item->ocr_confidence,
                    'created_by_type' => Teacher::class,
                    'created_by_id' => $teacher->id,
                ], 1);

                $item->update([
                    'status' => 'confirmed',
                    'confirmed_choice' => $row['choice'],
                    'response_id' => $response->id,
                ]);
                $confirmedItems[] = $item;
                $confirmed++;
            }

            $this->persistPendingNotes($batch, $teacher, $confirmedItems);

            $batch->update([
                'status' => 'confirmed',
                'pending_note_body' => null,
                'pending_note_files' => null,
            ]);
        });

        return ['confirmed' => $confirmed, 'skipped' => $skipped];
    }

    /**
     * @param  list<PaperUploadItem>  $confirmedItems
     */
    private function persistPendingNotes(PaperUploadBatch $batch, Teacher $teacher, array $confirmedItems): void
    {
        $body = filled($batch->pending_note_body) ? (string) $batch->pending_note_body : null;
        $pendingPdfs = is_array($batch->pending_note_files) ? $batch->pending_note_files : [];
        $classFormId = (int) $batch->class_form_id;

        $attachments = [];
        foreach ($confirmedItems as $item) {
            if (! $item->image_path || ! Storage::disk('local')->exists($item->image_path)) {
                continue;
            }
            $ext = pathinfo($item->image_path, PATHINFO_EXTENSION) ?: 'jpg';
            $dest = 'class-form-notes/'.$classFormId.'/'.uniqid('paper_', true).'.'.$ext;
            Storage::disk('local')->copy($item->image_path, $dest);
            $attachments[] = [
                'path' => $dest,
                'name' => 'phieu-'.$item->id.'.'.$ext,
                'mime' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext),
                'size' => Storage::disk('local')->size($dest),
            ];
        }
        foreach ($pendingPdfs as $pdf) {
            if (! is_array($pdf) || empty($pdf['path']) || ! Storage::disk('local')->exists($pdf['path'])) {
                continue;
            }
            $attachments[] = [
                'path' => $pdf['path'],
                'name' => $pdf['name'] ?? basename($pdf['path']),
                'mime' => $pdf['mime'] ?? 'application/pdf',
                'size' => $pdf['size'] ?? Storage::disk('local')->size($pdf['path']),
            ];
        }

        if ($body === null && $attachments === []) {
            return;
        }

        if ($attachments === []) {
            ClassFormNote::query()->create([
                'class_form_id' => $classFormId,
                'teacher_id' => $teacher->id,
                'body' => $body,
                'file_path' => null,
                'file_name' => null,
                'mime' => null,
                'file_size' => null,
            ]);
        } else {
            foreach ($attachments as $i => $file) {
                ClassFormNote::query()->create([
                    'class_form_id' => $classFormId,
                    'teacher_id' => $teacher->id,
                    'body' => $i === 0 ? $body : null,
                    'file_path' => $file['path'],
                    'file_name' => $file['name'],
                    'mime' => $file['mime'],
                    'file_size' => $file['size'],
                ]);
            }
        }

        if ($body !== null) {
            $batch->classForm()->update(['teacher_note' => $body]);
        }
    }
}
