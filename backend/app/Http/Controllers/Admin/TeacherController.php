<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MiniAppUser;
use App\Models\Teacher;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $teachers = Teacher::query()
            ->with('school')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('zalo_id', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.teachers.index', compact('teachers', 'q'));
    }

    public function update(Request $request, Teacher $teacher, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,disabled'],
        ]);

        $previous = $teacher->status;
        $teacher->update(['status' => $data['status']]);

        if ($data['status'] === 'disabled') {
            MiniAppUser::query()
                ->where('teacher_id', $teacher->id)
                ->each(function (MiniAppUser $user): void {
                    $user->tokens()->delete();
                    if ($user->role === 'teacher') {
                        $user->update(['role' => 'parent', 'teacher_id' => null]);
                    }
                });
        }

        $audit->record('admin.teacher.update', auth()->user(), $teacher, [
            'status_from' => $previous,
            'status_to' => $data['status'],
        ], $request);

        $message = $data['status'] === 'disabled'
            ? 'Đã khóa tài khoản giáo viên.'
            : 'Đã mở khóa tài khoản giáo viên.';

        return redirect()
            ->route('admin.teachers.index', $request->only('q'))
            ->with('status', $message);
    }
}
