<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaperOcrBatchJob;
use App\Models\ClassForm;
use App\Models\ClassFormNote;
use App\Models\ClassProfile;
use App\Models\Form;
use App\Models\PaperUploadBatch;
use App\Models\PaperUploadItem;
use App\Models\Response;
use App\Models\Teacher;
use App\Services\AuditLogService;
use App\Services\ClassFormLinkService;
use App\Services\CoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassFormController extends Controller
{
    public function ensure(Request $request, ClassProfile $profile, Form $form, ClassFormLinkService $links, AuditLogService $audit): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        abort_unless((int) $profile->created_by_teacher_id === (int) $teacher->id, 403);
        abort_unless($form->status === 'active' && $form->isActive(), 422, 'Form không active.');

        $result = $links->ensure($profile, $form, false);
        $message = 'Đã lấy link vote. Link giữ nguyên đến khi đóng form; đóng/mở lại không đổi mã link.';

        $voteUrl = $links->voteUrl($result['plain_token']);
        $request->session()->put('class_form_tokens.'.$result['class_form']->id, $result['plain_token']);

        $audit->record('class_form.ensure_link', $teacher, $result['class_form'], [
            'rotated' => false,
        ], $request);

        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json([
                'class_form_id' => $result['class_form']->id,
                'vote_url' => $voteUrl,
                'status' => $result['class_form']->status,
                'message' => $message,
                'detail_url' => route('teacher.class-forms.show', $result['class_form']),
            ]);
        }

        return redirect()
            ->route('teacher.class-forms.show', $result['class_form'])
            ->with('status', $message);
    }

    public function show(Request $request, ClassForm $classForm, CoverageService $coverage, ClassFormLinkService $links): View
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load(['form', 'classProfile.school.ward.province']);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $plain = $links->ensurePersistedToken($classForm);
        $classForm->refresh();
        $voteUrl = $links->voteUrl($plain);
        $request->session()->put('class_form_tokens.'.$classForm->id, $plain);
        $stats = $coverage->stats($classForm);
        $responses = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('status', 'valid')
            ->latest()
            ->paginate(50);

        $zaloIds = $responses->getCollection()->pluck('zalo_user_id')->filter()->unique()->values();
        $zaloUsers = $zaloIds->isEmpty()
            ? collect()
            : \App\Models\MiniAppUser::query()
                ->whereIn('zalo_user_id', $zaloIds)
                ->get(['zalo_user_id', 'name', 'phone'])
                ->keyBy('zalo_user_id');
        $zaloNames = $zaloUsers->map->name;
        $zaloPhones = $zaloUsers->map->phone;

        $notes = ClassFormNote::query()
            ->with('teacher')
            ->where('class_form_id', $classForm->id)
            ->latest()
            ->get();

        $profiles = ClassProfile::query()
            ->with(['school'])
            ->where('created_by_teacher_id', $teacher->id)
            ->orderBy('class_name')
            ->get();

        return view('teacher.class-forms.show', [
            'classForm' => $classForm,
            'coverage' => $stats['coverage'],
            'stats' => $stats,
            'voteUrl' => $voteUrl,
            'responses' => $responses,
            'zaloNames' => $zaloNames,
            'zaloPhones' => $zaloPhones,
            'notes' => $notes,
            'profiles' => $profiles,
            'canEditPaper' => $coverage->canTeacherEditPaperArtifacts($classForm),
            'hasTemplate' => filled($classForm->form?->consent_pdf_path)
                && \Illuminate\Support\Facades\Storage::disk('local')->exists((string) $classForm->form->consent_pdf_path),
        ]);
    }

    public function storeNote(Request $request, ClassForm $classForm, AuditLogService $audit): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load('classProfile');
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:50'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            // Backward compat: single file field
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        /** @var list<UploadedFile> $uploads */
        $uploads = array_values(array_filter([
            ...($request->file('files') ?: []),
            $request->file('file'),
        ]));

        if (! filled($data['body'] ?? null) && $uploads === []) {
            return back()->withErrors(['body' => 'Nhập ghi chú hoặc chọn file đính kèm.']);
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

        $createdNote = false;

        // Text / PDF only → lưu ghi chú ngay. Ảnh phiếu → OCR; ghi chú + đính kèm ảnh chỉ lưu khi xác nhận kết quả.
        if ($imageFiles === [] && (filled($data['body'] ?? null) || $noteFiles !== [])) {
            if ($noteFiles === []) {
                ClassFormNote::query()->create([
                    'class_form_id' => $classForm->id,
                    'teacher_id' => $teacher->id,
                    'body' => $data['body'] ?: null,
                    'file_path' => null,
                    'file_name' => null,
                    'mime' => null,
                    'file_size' => null,
                ]);
                $createdNote = true;
            } else {
                foreach ($noteFiles as $i => $file) {
                    $path = $file->store('class-form-notes/'.$classForm->id, 'local');
                    ClassFormNote::query()->create([
                        'class_form_id' => $classForm->id,
                        'teacher_id' => $teacher->id,
                        'body' => $i === 0 ? ($data['body'] ?: null) : null,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                    $createdNote = true;
                }
            }

            if (filled($data['body'] ?? null)) {
                $classForm->update(['teacher_note' => $data['body']]);
            }

            $audit->record('class_form.teacher_note', $teacher, $classForm, [
                'has_note' => true,
                'has_file' => $noteFiles !== [],
                'ocr_images' => 0,
            ], $request);

            return back()->with('status', 'Đã thêm ghi chú.');
        }

        if ($imageFiles !== []) {
            $pendingPdfs = [];
            foreach ($noteFiles as $file) {
                $path = $file->store('class-form-notes/'.$classForm->id.'/pending', 'local');
                $pendingPdfs[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $batch = PaperUploadBatch::query()->create([
                'class_form_id' => $classForm->id,
                'teacher_id' => $teacher->id,
                'status' => 'processing',
                'pending_note_body' => $data['body'] ?: null,
                'pending_note_files' => $pendingPdfs !== [] ? $pendingPdfs : null,
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
                'via' => 'class_form_note',
                'pending_note' => filled($data['body'] ?? null),
            ], $request);

            $payload = [
                'batch_id' => $batch->id,
                'embed_url' => route('teacher.paper.show', ['batch' => $batch, 'embed' => 1]),
                'message' => 'Đang OCR '.count($imageFiles).' ảnh… Ghi chú sẽ lưu khi xác nhận kết quả.',
            ];

            if ($request->expectsJson() || $request->boolean('ajax')) {
                return response()->json($payload);
            }

            return redirect()
                ->route('teacher.paper.show', $batch)
                ->with('status', $payload['message']);
        }

        return back()->with('status', 'Đã thêm ghi chú.');
    }

    public function destroyNote(Request $request, ClassFormNote $note, CoverageService $coverage, AuditLogService $audit): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $note->load('classForm.classProfile', 'classForm.form');
        abort_unless($note->classForm->classProfile->created_by_teacher_id === $teacher->id, 403);
        abort_unless($coverage->canTeacherEditPaperArtifacts($note->classForm), 422, 'Form/vote đã đóng — không xóa ghi chú được.');

        if ($note->hasFile() && Storage::disk('local')->exists($note->file_path)) {
            Storage::disk('local')->delete($note->file_path);
        }
        $note->delete();

        $audit->record('class_form.note_delete', $teacher, $note->classForm, [
            'note_id' => $note->id,
        ], $request);

        return back()->with('status', 'Đã xóa ghi chú.');
    }

    public function destroyPaperResponse(Request $request, Response $response, CoverageService $coverage, AuditLogService $audit): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $response->load('classForm.classProfile', 'classForm.form');
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
        ], $request);

        return back()->with('status', 'Đã xóa kết quả phiếu giấy.');
    }

    public function downloadNoteFile(Request $request, ClassFormNote $note): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $note->load('classForm.classProfile');
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

    public function showResponsePaper(Request $request, Response $response): View|StreamedResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $response->load(['classForm.classProfile', 'classForm.form']);
        abort_unless($response->classForm?->classProfile?->created_by_teacher_id === $teacher->id, 403);
        abort_unless($response->channel === 'paper', 404);
        abort_unless(filled($response->image_path) && Storage::disk('local')->exists($response->image_path), 404);

        if ($request->boolean('raw')) {
            return Storage::disk('local')->response($response->image_path);
        }

        return view('teacher.paper.response', [
            'response' => $response,
            'classForm' => $response->classForm,
            'form' => $response->classForm?->form,
        ]);
    }

    public function close(Request $request, ClassForm $classForm, AuditLogService $audit): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load('classProfile');
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $classForm->update(['status' => 'closed']);

        $audit->record('class_form.close', $teacher, $classForm, null, $request);

        return back()->with('status', 'Đã đóng vote. Phụ huynh không còn gửi / đổi ý được.');
    }

    public function reopen(Request $request, ClassForm $classForm, AuditLogService $audit): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load(['classProfile', 'form']);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );
        abort_unless($classForm->form && $classForm->form->isActive(), 422, 'Form admin đã đóng hoặc hết hạn, không mở lại được.');

        $coverage = app(CoverageService::class)->currentCoverage($classForm);
        $quota = (int) $classForm->classProfile->quota;
        $status = $coverage >= $quota ? 'quota_full' : 'open';
        $classForm->update(['status' => $status]);

        $audit->record('class_form.reopen', $teacher, $classForm, [
            'status' => $status,
        ], $request);

        return back()->with('status', 'Đã mở lại vote cho lớp này.');
    }

    public function downloadTemplate(Request $request, ClassForm $classForm): StreamedResponse|RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load(['classProfile', 'form']);
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $form = $classForm->form;
        if (! $form || ! filled($form->consent_pdf_path) || ! Storage::disk('local')->exists($form->consent_pdf_path)) {
            return back()->withErrors(['template' => 'Form chưa có file mẫu phiếu PDF. Liên hệ Admin tải mẫu lên Form.']);
        }

        $name = 'mau-phieu-'.\Illuminate\Support\Str::slug($form->title).'.pdf';

        return Storage::disk('local')->download($form->consent_pdf_path, $name);
    }

    public function downloadQr(Request $request, ClassForm $classForm, ClassFormLinkService $links): \Illuminate\Http\Response|RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        $classForm->load('classProfile');
        abort_unless(
            (int) $classForm->classProfile->created_by_teacher_id === (int) $teacher->id,
            403,
            'Bạn không có quyền xem form lớp này.',
        );

        $voteUrl = $links->voteUrlFor($classForm);
        if (! $voteUrl) {
            return back()->withErrors(['qr' => 'Chưa có link vote để tạo QR.']);
        }

        $api = 'https://api.qrserver.com/v1/create-qr-code/?size=440x440&margin=8&data='.urlencode($voteUrl);
        $response = Http::timeout(15)->get($api);
        if (! $response->successful() || $response->body() === '') {
            return back()->withErrors(['qr' => 'Không tạo được ảnh QR. Thử lại sau.']);
        }

        $filename = 'vote-qr-'.$classForm->id.'.png';

        return response($response->body(), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function downloadFormTemplate(Request $request, Form $form): StreamedResponse|RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('teacher');
        abort_unless($teacher instanceof Teacher, 403);

        if (! filled($form->consent_pdf_path) || ! Storage::disk('local')->exists($form->consent_pdf_path)) {
            return back()->withErrors(['template' => 'Form chưa có file mẫu phiếu PDF. Liên hệ Admin tải mẫu lên Form.']);
        }

        $name = 'mau-phieu-'.\Illuminate\Support\Str::slug($form->title).'.pdf';

        return Storage::disk('local')->download($form->consent_pdf_path, $name);
    }
}
