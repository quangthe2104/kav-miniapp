<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaperOcrBatchJob;
use App\Models\ClassForm;
use App\Models\PaperUploadBatch;
use App\Models\PaperUploadItem;
use App\Models\Teacher;
use App\Services\AuditLogService;
use App\Services\PaperBatchConfirmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PaperBatchController extends Controller
{
    public function create(Request $request, ClassForm $classForm): View
    {
        $this->authorizeClassForm($request, $classForm);
        $classForm->loadMissing(['form', 'classProfile']);

        return view('teacher.paper.create', compact('classForm'));
    }

    public function store(Request $request, ClassForm $classForm, AuditLogService $audit): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $this->authorizeClassForm($request, $classForm);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:50'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $batch = PaperUploadBatch::query()->create([
            'class_form_id' => $classForm->id,
            'teacher_id' => $teacher->id,
            'status' => 'processing',
        ]);

        foreach ($request->file('images') as $file) {
            $path = $file->store('paper-uploads/'.$batch->id, 'local');
            PaperUploadItem::query()->create([
                'paper_upload_batch_id' => $batch->id,
                'image_path' => $path,
                'status' => 'pending',
            ]);
        }

        ProcessPaperOcrBatchJob::dispatch($batch->id);

        $audit->record('paper.upload', $teacher, $batch, [
            'class_form_id' => $classForm->id,
            'images' => count($request->file('images')),
        ], $request);

        return redirect()->route('teacher.paper.show', $batch)->with('status', 'Đã upload. Đang OCR… (refresh nếu chưa xong)');
    }

    public function show(Request $request, PaperUploadBatch $batch): View
    {
        $batch->load(['items', 'classForm.form', 'classForm.classProfile']);
        abort_unless($batch->classForm->classProfile->created_by_teacher_id === $request->attributes->get('teacher')->id, 403);

        $view = $request->boolean('embed') ? 'teacher.paper.show_embed' : 'teacher.paper.show';

        return view($view, compact('batch'));
    }

    public function confirm(Request $request, PaperUploadBatch $batch, PaperBatchConfirmService $confirm, AuditLogService $audit): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $batch->loadMissing(['classForm.form', 'classForm.classProfile']);
        abort_unless($batch->classForm->classProfile->created_by_teacher_id === $teacher->id, 403);

        $form = $batch->classForm->form;
        $allowed = array_merge($form?->allowedChoiceValues() ?? ['agree', 'disagree'], ['skip']);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.choice' => ['required', 'string', Rule::in($allowed)],
        ]);

        try {
            $result = $confirm->confirm($batch, $teacher, $data['items']);
        } catch (RuntimeException $e) {
            if ($request->expectsJson() || $request->boolean('ajax')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['paper' => $e->getMessage()]);
        }

        $confirmed = $result['confirmed'];
        $skipped = $result['skipped'];

        $audit->record('paper.confirm', $teacher, $batch, [
            'confirmed' => $confirmed,
            'skipped' => $skipped,
        ], $request);

        $message = 'Đã xác nhận phiếu giấy'.($confirmed ? ' và lưu ghi chú đính kèm' : '').'.';

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => route('teacher.class-forms.show', $batch->class_form_id),
            ]);
        }

        return redirect()
            ->route('teacher.class-forms.show', $batch->class_form_id)
            ->with('status', $message);
    }

    public function showImage(Request $request, PaperUploadItem $item): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $item->load('batch.classForm.classProfile');
        abort_unless(
            $item->batch?->classForm?->classProfile?->created_by_teacher_id === $request->attributes->get('teacher')->id,
            403
        );
        abort_unless($item->image_path && Storage::disk('local')->exists($item->image_path), 404);

        return Storage::disk('local')->response($item->image_path);
    }

    private function authorizeClassForm(Request $request, ClassForm $classForm): void
    {
        $classForm->loadMissing('classProfile');
        abort_unless($classForm->classProfile->created_by_teacher_id === $request->attributes->get('teacher')->id, 403);
    }
}
