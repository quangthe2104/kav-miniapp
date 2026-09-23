<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\AuditLogService;
use App\Services\TeacherProvisionService;
use App\Services\ZaloAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('teacher.login', [
            'zaloReady' => filled(config('services.zalo.app_id')) && filled(config('services.zalo.app_secret')),
            'devLogin' => (bool) config('services.zalo.dev_login'),
        ]);
    }

    public function redirectToZalo(Request $request, ZaloAuthService $zalo): RedirectResponse
    {
        try {
            $flow = $zalo->beginWebOAuth();
        } catch (RuntimeException $e) {
            return back()->withErrors(['auth' => $e->getMessage()]);
        }

        $request->session()->put('zalo_oauth', [
            'state' => $flow['state'],
            'code_verifier' => $flow['code_verifier'],
        ]);

        return redirect()->away($flow['authorize_url']);
    }

    public function handleZaloCallback(
        Request $request,
        ZaloAuthService $zalo,
        AuditLogService $audit,
        TeacherProvisionService $provision,
    ): RedirectResponse {
        $saved = $request->session()->pull('zalo_oauth');
        if (! is_array($saved)) {
            return redirect()->route('teacher.login')->withErrors(['auth' => 'Phiên Zalo OAuth hết hạn. Thử lại.']);
        }

        if ($request->query('error')) {
            return redirect()->route('teacher.login')->withErrors(['auth' => 'Zalo từ chối đăng nhập: '.$request->query('error')]);
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        if ($state === '' || ! hash_equals((string) $saved['state'], $state) || $code === '') {
            return redirect()->route('teacher.login')->withErrors(['auth' => 'State/code Zalo không hợp lệ.']);
        }

        try {
            $tokens = $zalo->exchangeWebCode($code, (string) $saved['code_verifier']);
            $profile = $zalo->fetchProfile($tokens['access_token']);
        } catch (RuntimeException $e) {
            return redirect()->route('teacher.login')->withErrors(['auth' => $e->getMessage()]);
        }

        $existing = $provision->findByZaloId($profile['id']);
        if ($provision->isLocked($existing)) {
            $audit->record('teacher.login_failed', null, $existing, [
                'zalo_id' => $profile['id'],
                'via' => 'oauth',
                'reason' => 'disabled',
            ], $request);

            return redirect()->route('teacher.login')->withErrors([
                'auth' => TeacherProvisionService::LOCKED_MESSAGE,
            ]);
        }

        ['teacher' => $teacher, 'created' => $created] = $provision->findOrCreateActive(
            $profile['id'],
            $profile['name'] ?? null,
            null,
            $audit,
            $request,
            'oauth',
        );

        $request->session()->regenerate();
        $request->session()->put('teacher_id', $teacher->id);

        $audit->record('teacher.login', $teacher, $teacher, ['via' => 'oauth'], $request);

        $redirect = redirect()->route('teacher.dashboard');
        if ($created) {
            return $redirect->with('onboarding', true);
        }

        return $redirect->with('status', 'Đăng nhập Zalo thành công.');
    }

    public function devLogin(
        Request $request,
        AuditLogService $audit,
        TeacherProvisionService $provision,
    ): RedirectResponse {
        if (! config('services.zalo.dev_login')) {
            abort(403, 'Zalo dev login is disabled.');
        }

        $data = $request->validate([
            'phone' => ['nullable', 'string'],
            'zalo_id' => ['nullable', 'string'],
        ]);

        if (empty($data['phone']) && empty($data['zalo_id'])) {
            return back()->withErrors(['auth' => 'Cần ít nhất SĐT hoặc Zalo ID.'])->withInput();
        }

        $existing = null;
        if (! empty($data['zalo_id'])) {
            $existing = $provision->findByZaloId($data['zalo_id']);
        }
        if (! $existing && ! empty($data['phone'])) {
            $existing = $provision->findByPhone($data['phone']);
        }

        if ($provision->isLocked($existing)) {
            $audit->record('teacher.login_failed', null, $existing, [
                'phone' => $data['phone'] ?? null,
                'zalo_id' => $data['zalo_id'] ?? null,
                'via' => 'dev_login',
                'reason' => 'disabled',
            ], $request);

            return back()->withErrors(['auth' => TeacherProvisionService::LOCKED_MESSAGE])->withInput();
        }

        ['teacher' => $teacher, 'created' => $created] = $provision->findOrCreateFromCredentials(
            $data['zalo_id'] ?? null,
            $data['phone'] ?? null,
            null,
            $audit,
            $request,
            'dev_login',
        );

        $request->session()->regenerate();
        $request->session()->put('teacher_id', $teacher->id);

        $audit->record('teacher.login', $teacher, $teacher, [
            'via' => 'dev_login',
        ], $request);

        $redirect = redirect()->route('teacher.dashboard');
        if ($created) {
            return $redirect->with('onboarding', true);
        }

        return $redirect;
    }

    public function logout(Request $request, AuditLogService $audit): RedirectResponse
    {
        $teacherId = $request->session()->get('teacher_id');
        $teacher = $teacherId ? Teacher::query()->find($teacherId) : null;
        $audit->record('teacher.logout', $teacher, $teacher, null, $request);

        $request->session()->forget('teacher_id');

        return redirect()->route('teacher.login');
    }
}
