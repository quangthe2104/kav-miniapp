<?php

namespace App\Http\Middleware;

use App\Models\Teacher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        $teacherId = $request->session()->get('teacher_id');
        if (! $teacherId) {
            return redirect()->route('teacher.login')->withErrors(['auth' => 'Vui lòng đăng nhập bằng Zalo (hoặc tài khoản demo).']);
        }

        $teacher = Teacher::query()->find($teacherId);
        if (! $teacher || ! $teacher->isActive()) {
            $request->session()->forget('teacher_id');

            return redirect()->route('teacher.login')->withErrors(['auth' => 'Tài khoản giáo viên không hợp lệ hoặc đã bị khóa.']);
        }

        $request->attributes->set('teacher', $teacher);
        view()->share('currentTeacher', $teacher);

        return $next($request);
    }
}
