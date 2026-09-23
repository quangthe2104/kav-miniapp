<?php

namespace App\Http\Controllers\Api\Miniapp;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaperOcrBatchJob;
use App\Models\ClassForm;
use App\Models\ClassFormNote;
use App\Models\ClassProfile;
use App\Models\Form;
use App\Models\MiniAppUser;
use App\Models\PaperUploadBatch;
use App\Models\PaperUploadItem;
use App\Models\Province;
use App\Models\Response;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Ward;
use App\Services\AuditLogService;
use App\Services\ClassFormLinkService;
use App\Services\CoverageService;
use App\Services\PaperBatchConfirmService;
use App\Services\TeacherProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends Controller
{
    public function provinces(): JsonResponse
    {
        $rows = Province::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['provinces' => $rows]);
    }

    public function wards(Province $province): JsonResponse
    {
        $rows = Ward::query()
            ->where('province_id', $province->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['wards' => $rows]);
    }

    public function schools(Request $request, Ward $ward): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = School::query()
            ->where('ward_id', $ward->id)
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', '%'.$q.'%')
                    ->orWhere('external_id', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->limit(200)->get(['id', 'name', 'external_id']);

        return response()->json(['schools' => $rows]);
    }

    public function storeProfile(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'class_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('class_profiles')->where(fn ($q) => $q
                    ->where('school_id', $request->input('school_id'))
                    ->where('created_by_teacher_id', $teacher->id)),
            ],
            'quota' => ['required', 'integer', 'min:1', 'max:80'],
        ], [
            'class_name.unique' => 'Bạn đã có lớp này tại trường đã chọn.',
        ]);

        $profile = ClassProfile::query()->create([
            'school_id' => $data['school_id'],
            'class_name' => $data['class_name'],
            'quota' => $data['quota'],
            'created_by_teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        $profile->load(['school.ward.province']);

        return response()->json([
            'profile' => [
                'id' => $profile->id,
                'class_name' => $profile->class_name,
                'quota' => $profile->quota,
                'school' => $profile->school?->name,
                'external_id' => $profile->school?->external_id,
                'ward' => $profile->school?->ward?->name,
                'province' => $profile->school?->ward?->province?->name,
            ],
            'message' => 'Đã tạo lớp. Tiếp theo: lấy link gửi phụ huynh.',
        ], 201);
    }

    public function profiles(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $profiles = ClassProfile::query()
            ->with(['school.ward.province'])
            ->where('created_by_teacher_id', $teacher->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ClassProfile $p) => [
                'id' => $p->id,
                'class_name' => $p->class_name,
                'quota' => $p->quota,
                'school' => $p->school?->name,
                'external_id' => $p->school?->external_id,
                'ward' => $p->school?->ward?->name,
                'province' => $p->school?->ward?->province?->name,
            ]);

        return response()->json(['profiles' => $profiles]);
    }

    public function showProfile(Request $request, ClassProfile $profile, CoverageService $coverage): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $profile->created_by_teacher_id === (int) $teacher->id, 403);

        $statusFilter = (string) $request->query('status', 'open');
        if (! in_array($statusFilter, ['open', 'closed', 'all'], true)) {
            $statusFilter = 'open';
        }

        $profile->load(['school.ward.province', 'classForms.form']);
        $linkedByForm = $profile->classForms->keyBy('form_id');
        $activeForms = Form::query()->activeNow()->orderBy('title')->get();

        $rows = collect();
        foreach ($activeForms as $form) {
            $cf = $linkedByForm->get($form->id);
            if ($cf instanceof ClassForm) {
                $rows->push($this->formRow($cf, $profile, $coverage));
            } else {
                $rows->push([
                    'class_form_id' => null,
                    'form_id' => $form->id,
                    'title' => $form->title,
                    'status' => 'none',
                    'status_label' => 'Chưa mở link',
                    'coverage' => 0,
                    'coverage_pct' => 0,
                    'quota' => (int) $profile->quota,
                    'choice_counts' => collect($form->resolvedOptions()['choices'])->map(fn ($c) => [
                        'value' => $c['value'],
                        'label' => $c['label'],
                        'count' => 0,
                    ])->values()->all(),
                    'choice_compact' => collect($form->resolvedOptions()['choices'])->map(fn () => '0')->implode(' - '),
                    'choice_tooltip' => collect($form->resolvedOptions()['choices'])->map(fn ($c) => $c['label'].': 0')->implode("\n"),
                    'has_template' => filled($form->consent_pdf_path),
                ]);
            }
        }

        if ($statusFilter === 'closed') {
            $rows = $profile->classForms
                ->filter(fn (ClassForm $cf) => $cf->effectiveStatus() === 'closed')
                ->map(fn (ClassForm $cf) => $this->formRow($cf, $profile, $coverage))
                ->values();
        }

        return response()->json([
            'profile' => [
                'id' => $profile->id,
                'class_name' => $profile->class_name,
                'quota' => $profile->quota,
                'school' => $profile->school?->name,
                'ward' => $profile->school?->ward?->name,
                'province' => $profile->school?->ward?->province?->name,
            ],
            'status_filter' => $statusFilter,
            'forms' => $rows->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ]);
    }

    public function showClassForm(Request $request, int $classFormId, CoverageService $coverage, ClassFormLinkService $links): JsonResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with(['form', 'classProfile.school'])->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $plain = $links->ensurePersistedToken($classForm);
        $classForm->refresh();
        $voteUrl = $links->voteUrl($plain);

        $stats = $this->statsWithColors($classForm, $coverage);
        $quota = max(1, (int) $classForm->classProfile->quota);
        $canEditPaper = $coverage->canTeacherEditPaperArtifacts($classForm);
        $effectiveStatus = $classForm->effectiveStatus();
        $statusLabel = $this->statusLabel($effectiveStatus);

        $rawResponses = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('status', 'valid')
            ->latest()
            ->limit(200)
            ->get([
                'id', 'choice', 'channel', 'phone', 'zalo_user_id', 'created_at',
                'image_path', 'created_by_type', 'created_by_id',
            ]);

        $zaloNames = MiniAppUser::query()
            ->whereIn('zalo_user_id', $rawResponses->pluck('zalo_user_id')->filter()->unique()->values())
            ->pluck('name', 'zalo_user_id');

        $responses = $rawResponses->values()->map(function (Response $r, int $idx) use ($classForm, $zaloNames, $canEditPaper, $teacher) {
            $hasPaperImage = $r->channel === 'paper'
                && filled($r->image_path)
                && Storage::disk('local')->exists((string) $r->image_path);
            $canDelete = $canEditPaper
                && $r->channel === 'paper'
                && $r->created_by_type === Teacher::class
                && (int) $r->created_by_id === (int) $teacher->id;

            return [
                'id' => $r->id,
                'stt' => $idx + 1,
                'choice' => $r->choice,
                'choice_label' => $classForm->form?->choiceLabel((string) $r->choice),
                'channel' => $r->channel,
                'phone' => $r->phone,
                'zalo_user_id' => $r->zalo_user_id,
                'zalo_name' => $r->zalo_user_id ? ($zaloNames[$r->zalo_user_id] ?? null) : null,
                'has_paper_image' => $hasPaperImage,
                'paper_image_url' => $hasPaperImage
                    ? url('/api/miniapp/v1/teacher/responses/'.$r->id.'/paper?inline=1')
                    : null,
                'can_delete' => $canDelete,
                'created_at' => optional($r->created_at)?->toIso8601String(),
            ];
        });

        $notes = ClassFormNote::query()
            ->with('teacher')
            ->where('class_form_id', $classForm->id)
            ->latest()
            ->get()
            ->map(function (ClassFormNote $n) use ($canEditPaper) {
                $hasFile = $n->hasFile();
                $fileUrl = $hasFile
                    ? url('/api/miniapp/v1/teacher/class-form-notes/'.$n->id.'/file')
                    : null;

                return [
                    'id' => $n->id,
                    'body' => $n->body,
                    'file_name' => $n->file_name,
                    'has_file' => $hasFile,
                    'teacher_name' => $n->teacher?->name,
                    'is_image' => $hasFile && $n->isImage(),
                    'is_pdf' => $hasFile && $n->isPdf(),
                    'file_url' => $fileUrl,
                    'file_inline_url' => $hasFile
                        ? url('/api/miniapp/v1/teacher/class-form-notes/'.$n->id.'/file?inline=1')
                        : null,
                    'can_delete' => $canEditPaper,
                    'created_at' => optional($n->created_at)?->toIso8601String(),
                ];
            });

        return response()->json([
            'class_form' => [
                'id' => $classForm->id,
                'status' => $effectiveStatus,
                'form_active' => (bool) $classForm->form?->isActive(),
                'status_label' => $statusLabel,
                'can_edit_paper' => $canEditPaper,
                'form_id' => $classForm->form_id,
                'form_title' => $classForm->form?->title,
                'profile_id' => $classForm->class_profile_id,
                'class_name' => $classForm->classProfile->class_name,
                'school' => $classForm->classProfile->school?->name,
                'quota' => (int) $classForm->classProfile->quota,
                'has_template' => filled($classForm->form?->consent_pdf_path)
                    && Storage::disk('local')->exists((string) $classForm->form->consent_pdf_path),
                'vote_url' => $voteUrl,
            ],
            'stats' => [
                'coverage' => $stats['coverage'],
                'coverage_pct' => (int) min(100, round(($stats['coverage'] / $quota) * 100)),
                'total' => $stats['total'],
                'remaining' => $stats['remaining'],
                'remaining_color' => $stats['remaining_color'],
                'choice_counts' => $stats['choice_counts'],
            ],
            'responses' => $responses,
            'notes' => $notes,
        ]);
    }

    public function ensureLink(
        Request $request,
        ClassProfile $profile,
        Form $form,
        ClassFormLinkService $links,
        AuditLogService $audit,
    ): JsonResponse {
        $teacher = $this->teacher($request);
        abort_unless((int) $profile->created_by_teacher_id === (int) $teacher->id, 403);
        abort_unless($form->status === 'active' && $form->isActive(), 422, 'Form không active.');

        $result = $links->ensure($profile, $form, false);
        $plain = $result['plain_token'];
        $voteUrl = $plain ? $links->voteUrl($plain) : null;

        $audit->record('class_form.ensure_link', $teacher, $result['class_form'], [
            'rotated' => false,
            'via' => 'miniapp',
        ], $request);

        return response()->json([
            'class_form_id' => $result['class_form']->id,
            'status' => $result['class_form']->status,
            'vote_url' => $voteUrl,
            'message' => 'Đã lấy link. Link giữ nguyên đến khi đóng form.',
        ]);
    }

    public function close(Request $request, int $classFormId, AuditLogService $audit): JsonResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with('classProfile')->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $classForm->update(['status' => 'closed']);
        $audit->record('class_form.close', $teacher, $classForm, ['via' => 'miniapp'], $request);

        return response()->json(['status' => 'closed', 'message' => 'Đã đóng vote.']);
    }

    public function reopen(Request $request, int $classFormId, CoverageService $coverage, AuditLogService $audit): JsonResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with(['classProfile', 'form'])->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );
        abort_unless($classForm->form && $classForm->form->isActive(), 422, 'Form admin đã đóng hoặc hết hạn.');

        $cov = $coverage->currentCoverage($classForm);
        $quota = (int) $classForm->classProfile->quota;
        $status = $cov >= $quota ? 'quota_full' : 'open';
        $classForm->update(['status' => $status]);
        $audit->record('class_form.reopen', $teacher, $classForm, ['status' => $status, 'via' => 'miniapp'], $request);

        return response()->json(['status' => $status, 'message' => 'Đã mở lại vote.']);
    }

    public function storeNote(Request $request, int $classFormId, AuditLogService $audit): JsonResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with('classProfile')->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:50'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        /** @var list<UploadedFile> $uploads */
        $uploads = array_values(array_filter([
            ...($request->file('files') ?: []),
            $request->file('file'),
        ]));

        if (! filled($data['body'] ?? null) && $uploads === []) {
            return response()->json(['message' => 'Nhập ghi chú hoặc chọn file.'], 422);
        }

        $imageFiles = [];
        $noteFiles = [];
        foreach ($uploads as $upload) {
            $ext = strtolower($upload->getClientOriginalExtension() ?: '');
            $mime = (string) $upload->getClientMimeType();
            $isImage = str_starts_with($mime, 'image/')
                || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);

            if ($isImage) {
                $imageFiles[] = $upload;
            } else {
                $noteFiles[] = $upload;
            }
        }

        $note = null;
        if (filled($data['body'] ?? null) || $noteFiles !== []) {
            if ($noteFiles === []) {
                $note = ClassFormNote::query()->create([
                    'class_form_id' => $classForm->id,
                    'teacher_id' => $teacher->id,
                    'body' => $data['body'] ?: null,
                    'file_path' => null,
                    'file_name' => null,
                    'mime' => null,
                    'file_size' => null,
                ]);
            } else {
                foreach ($noteFiles as $i => $file) {
                    $path = $file->store('class-form-notes/'.$classForm->id, 'local');
                    $created = ClassFormNote::query()->create([
                        'class_form_id' => $classForm->id,
                        'teacher_id' => $teacher->id,
                        'body' => $i === 0 ? ($data['body'] ?: null) : null,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                    if ($i === 0) {
                        $note = $created;
                    }
                }
            }
        }

        if (filled($data['body'] ?? null)) {
            $classForm->update(['teacher_note' => $data['body']]);
        }

        $audit->record('class_form.teacher_note', $teacher, $classForm, [
            'has_note' => (bool) $note || filled($data['body'] ?? null),
            'has_file' => $noteFiles !== [],
            'ocr_images' => count($imageFiles),
            'via' => 'miniapp',
        ], $request);

        $ocr = null;
        if ($imageFiles !== []) {
            $batch = PaperUploadBatch::query()->create([
                'class_form_id' => $classForm->id,
                'teacher_id' => $teacher->id,
                'status' => 'processing',
            ]);

            foreach ($imageFiles as $file) {
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
                'images' => count($imageFiles),
                'via' => 'miniapp_note',
            ], $request);

            $ocr = [
                'batch_id' => $batch->id,
                'images' => count($imageFiles),
                'confirm_url' => route('teacher.paper.show', $batch),
            ];
        }

        $message = $ocr
            ? ('Đã gửi OCR '.count($imageFiles).' ảnh. Xác nhận kết quả trong popup.')
            : 'Đã thêm ghi chú.';

        return response()->json([
            'note' => $note ? [
                'id' => $note->id,
                'body' => $note->body,
                'file_name' => $note->file_name,
                'has_file' => $note->hasFile(),
                'created_at' => optional($note->created_at)?->toIso8601String(),
            ] : null,
            'ocr' => $ocr,
            'message' => $message,
        ], 201);
    }

    public function downloadTemplate(Request $request, int $classFormId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with(['classProfile', 'form'])->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $form = $classForm->form;
        abort_unless($form && filled($form->consent_pdf_path) && Storage::disk('local')->exists($form->consent_pdf_path), 404, 'Chưa có mẫu phiếu PDF.');

        return Storage::disk('local')->download(
            $form->consent_pdf_path,
            'mau-phieu-'.\Illuminate\Support\Str::slug($form->title).'.pdf',
        );
    }

    public function downloadNoteFile(Request $request, int $noteId): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $teacher = $this->teacher($request);
        $note = ClassFormNote::query()->with('classForm.classProfile')->findOrFail($noteId);
        abort_unless($note->classForm->classProfile->created_by_teacher_id === $teacher->id, 403);
        abort_unless($note->hasFile() && Storage::disk('local')->exists($note->file_path), 404);

        $filename = $note->file_name ?: basename($note->file_path);

        if ($request->boolean('inline') || $request->boolean('raw')) {
            return Storage::disk('local')->response($note->file_path, $filename, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return Storage::disk('local')->download($note->file_path, $filename);
    }

    public function destroyNote(Request $request, int $noteId, CoverageService $coverage, AuditLogService $audit): JsonResponse
    {
        $teacher = $this->teacher($request);
        $note = ClassFormNote::query()->with('classForm.classProfile', 'classForm.form')->findOrFail($noteId);
        abort_unless($note->classForm->classProfile->created_by_teacher_id === $teacher->id, 403);
        abort_unless($coverage->canTeacherEditPaperArtifacts($note->classForm), 422, 'Form/vote đã đóng — không xóa ghi chú được.');

        if ($note->hasFile() && Storage::disk('local')->exists($note->file_path)) {
            Storage::disk('local')->delete($note->file_path);
        }
        $noteIdDeleted = $note->id;
        $classForm = $note->classForm;
        $note->delete();

        $audit->record('class_form.note_delete', $teacher, $classForm, [
            'note_id' => $noteIdDeleted,
            'via' => 'miniapp',
        ], $request);

        return response()->json(['message' => 'Đã xóa ghi chú.']);
    }

    public function showResponsePaper(Request $request, int $responseId): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $teacher = $this->teacher($request);
        $response = Response::query()->with('classForm.classProfile')->findOrFail($responseId);
        abort_unless($response->classForm?->classProfile?->created_by_teacher_id === $teacher->id, 403);
        abort_unless($response->channel === 'paper', 404);
        abort_unless(filled($response->image_path) && Storage::disk('local')->exists($response->image_path), 404);

        $filename = basename((string) $response->image_path);

        if ($request->boolean('inline') || $request->boolean('raw')) {
            return Storage::disk('local')->response($response->image_path, $filename, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return Storage::disk('local')->download($response->image_path, $filename);
    }

    public function destroyPaperResponse(Request $request, int $responseId, CoverageService $coverage, AuditLogService $audit): JsonResponse
    {
        $teacher = $this->teacher($request);
        $response = Response::query()->with('classForm.classProfile', 'classForm.form')->findOrFail($responseId);
        abort_unless($response->classForm->classProfile->created_by_teacher_id === $teacher->id, 403);
        abort_unless($response->channel === 'paper', 422, 'Chỉ xóa được phiếu giấy do giáo viên tải lên.');
        abort_unless($coverage->canTeacherEditPaperArtifacts($response->classForm), 422, 'Form/vote đã đóng — không xóa phiếu được.');
        abort_unless(
            $response->created_by_type === Teacher::class && (int) $response->created_by_id === (int) $teacher->id,
            403,
            'Chỉ xóa phiếu giấy do chính bạn tải lên.'
        );

        $coverage->voidResponse($response);

        $audit->record('paper.response_void', $teacher, $response->classForm, [
            'response_id' => $response->id,
            'via' => 'miniapp',
        ], $request);

        return response()->json(['message' => 'Đã xóa kết quả phiếu giấy.']);
    }

    public function showPaperBatch(Request $request, int $batchId): JsonResponse
    {
        $teacher = $this->teacher($request);
        $batch = PaperUploadBatch::query()
            ->with(['items', 'classForm.form', 'classForm.classProfile'])
            ->findOrFail($batchId);
        abort_unless($batch->classForm?->classProfile?->created_by_teacher_id === $teacher->id, 403);

        $form = $batch->classForm?->form;
        $resolved = $form?->resolvedOptions() ?? Form::defaultOptions();
        $choices = $resolved['choices'];
        $labels = Form::ocrSummaryLabels($form);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'agree_count' => (int) $batch->agree_count,
            'disagree_count' => (int) $batch->disagree_count,
            'unknown_count' => (int) $batch->unknown_count,
            'agree_label' => $labels['agree_label'],
            'other_label' => $labels['other_label'],
            'unknown_label' => $labels['unknown_label'],
            'choices' => $choices,
            'items' => $batch->items->map(function (PaperUploadItem $item) use ($form, $labels) {
                $suggestion = (string) ($item->ocr_suggestion ?? '');
                $label = ($suggestion !== '' && $suggestion !== 'unknown')
                    ? ($form?->choiceLabel($suggestion) ?? $suggestion)
                    : $labels['unknown_label'];
                $confidence = $item->ocr_confidence;

                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'ocr_suggestion' => $suggestion !== '' ? $suggestion : null,
                    'ocr_suggestion_label' => $label,
                    'ocr_confidence' => $confidence,
                    'ocr_confidence_pct' => $confidence !== null ? (int) round(((float) $confidence) * 100) : null,
                ];
            })->values(),
        ]);
    }

    public function showPaperItemImage(Request $request, int $itemId): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $teacher = $this->teacher($request);
        $item = PaperUploadItem::query()->with('batch.classForm.classProfile')->findOrFail($itemId);
        abort_unless($item->batch?->classForm?->classProfile?->created_by_teacher_id === $teacher->id, 403);
        abort_unless($item->image_path && Storage::disk('local')->exists($item->image_path), 404);

        $filename = basename((string) $item->image_path);

        return Storage::disk('local')->response($item->image_path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function confirmPaperBatch(
        Request $request,
        int $batchId,
        PaperBatchConfirmService $confirm,
        AuditLogService $audit,
    ): JsonResponse {
        $teacher = $this->teacher($request);
        $batch = PaperUploadBatch::query()
            ->with(['classForm.form', 'classForm.classProfile'])
            ->findOrFail($batchId);
        abort_unless($batch->classForm?->classProfile?->created_by_teacher_id === $teacher->id, 403);
        abort_unless($batch->status === 'ready', 422, 'OCR chưa xong hoặc batch đã xác nhận.');

        $form = $batch->classForm?->form;
        $allowed = array_merge($form?->allowedChoiceValues() ?? ['agree', 'disagree'], ['skip']);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.choice' => ['required', 'string', Rule::in($allowed)],
        ]);

        try {
            $result = $confirm->confirm($batch, $teacher, $data['items']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $audit->record('paper.confirm', $teacher, $batch, [
            'confirmed' => $result['confirmed'],
            'skipped' => $result['skipped'],
            'via' => 'miniapp',
        ], $request);

        return response()->json([
            'ok' => true,
            'message' => 'Đã xác nhận phiếu giấy.',
            'confirmed' => $result['confirmed'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function downloadQr(Request $request, int $classFormId, ClassFormLinkService $links): \Illuminate\Http\Response
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with('classProfile')->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $plain = $links->ensurePersistedToken($classForm);
        $voteUrl = $links->voteUrl($plain);
        abort_unless($voteUrl !== '', 422, 'Chưa có link vote để tạo QR.');

        $api = 'https://api.qrserver.com/v1/create-qr-code/?size=440x440&margin=8&data='.urlencode($voteUrl);
        $response = Http::timeout(15)->get($api);
        abort_unless($response->successful() && $response->body() !== '', 502, 'Không tạo được ảnh QR. Thử lại sau.');

        $filename = 'vote-qr-'.$classForm->id.'.png';

        return response($response->body(), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function responses(Request $request, int $classFormId): JsonResponse
    {
        $teacher = $this->teacher($request);
        $classForm = ClassForm::query()->with(['classProfile', 'form'])->findOrFail($classFormId);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $rows = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('status', 'valid')
            ->latest()
            ->limit(200)
            ->get(['id', 'choice', 'channel', 'phone', 'zalo_user_id', 'created_at']);

        return response()->json([
            'responses' => $rows->map(fn (Response $r) => [
                'id' => $r->id,
                'choice' => $r->choice,
                'choice_label' => $classForm->form?->choiceLabel((string) $r->choice),
                'channel' => $r->channel,
                'phone' => $r->phone,
                'zalo_user_id' => $r->zalo_user_id,
                'created_at' => optional($r->created_at)?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array{
     *     coverage: int,
     *     agree: int,
     *     disagree: int,
     *     total: int,
     *     by_choice: array<string, int>,
     *     choice_counts: list<array{value: string, label: string, count: int, color: string}>,
     *     remaining: int,
     *     remaining_color: string
     * }
     */
    private function statsWithColors(ClassForm $classForm, CoverageService $coverage): array
    {
        $stats = $coverage->stats($classForm);
        $quota = max(1, (int) $classForm->classProfile->quota);
        $remaining = max(0, $quota - (int) $stats['coverage']);
        $choiceValues = array_map(fn ($c) => (string) $c['value'], $stats['choice_counts']);
        $colors = $classForm->form?->choiceChartColors($choiceValues) ?? [];

        $choiceCounts = [];
        foreach ($stats['choice_counts'] as $i => $c) {
            $choiceCounts[] = [
                'value' => (string) $c['value'],
                'label' => (string) $c['label'],
                'count' => (int) $c['count'],
                'color' => $colors[$i] ?? '#94a3b8',
            ];
        }

        return [
            ...$stats,
            'choice_counts' => $choiceCounts,
            'remaining' => $remaining,
            'remaining_color' => Form::remainingChartColor(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Đang mở',
            'quota_full' => 'Đủ sĩ số',
            'closed' => 'Đã đóng',
            default => $status,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formRow(ClassForm $cf, ClassProfile $profile, CoverageService $coverage): array
    {
        $stats = $coverage->stats($cf);
        $quota = max(1, (int) $profile->quota);
        $pct = (int) min(100, round(($stats['coverage'] / $quota) * 100));
        $status = $cf->effectiveStatus();
        $statusLabel = $this->statusLabel($status);

        return [
            'class_form_id' => $cf->id,
            'form_id' => $cf->form_id,
            'title' => $cf->form?->title ?? 'Form #'.$cf->form_id,
            'status' => $status,
            'status_label' => $statusLabel,
            'coverage' => $stats['coverage'],
            'coverage_pct' => $pct,
            'quota' => (int) $profile->quota,
            'choice_counts' => $stats['choice_counts'],
            'choice_compact' => collect($stats['choice_counts'])->map(fn ($c) => (string) $c['count'])->implode(' - ') ?: '0',
            'choice_tooltip' => collect($stats['choice_counts'])->map(fn ($c) => $c['label'].': '.$c['count'])->implode("\n") ?: 'Chưa có phiếu',
            'has_template' => filled($cf->form?->consent_pdf_path),
        ];
    }

    private function teacher(Request $request): \App\Models\Teacher
    {
        /** @var MiniAppUser $user */
        $user = $request->user();
        abort_unless($user->isTeacher(), 403, 'Chỉ giáo viên đã đăng ký.');

        $teacher = $user->teacher()->firstOrFail();
        if (! $teacher->isActive()) {
            $user->tokens()->delete();
            abort(403, TeacherProvisionService::LOCKED_MESSAGE);
        }

        return $teacher;
    }
}
