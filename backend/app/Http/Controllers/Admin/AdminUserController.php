<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->where('is_admin', true)
            ->orderBy('email')
            ->paginate(30);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,disabled'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
            'status' => $data['status'],
        ]);

        $audit->record('admin.users.create', $request->user(), $user, [
            'email' => $user->email,
            'status' => $user->status,
        ], $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Đã tạo tài khoản admin.');
    }

    public function edit(User $user): View
    {
        $this->ensureAdminUser($user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user, AuditLogService $audit): RedirectResponse
    {
        $this->ensureAdminUser($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,disabled'],
        ]);

        if ($user->id === $request->user()->id && $data['status'] === 'disabled') {
            return back()
                ->withErrors(['status' => 'Không thể khóa tài khoản đang đăng nhập.'])
                ->withInput();
        }

        $before = $user->only(['name', 'email', 'status']);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        $audit->record('admin.users.update', $request->user(), $user, [
            'email' => $user->email,
            'before' => $before,
            'after' => $user->only(['name', 'email', 'status']),
            'password_reset' => ! empty($data['password']),
        ], $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Đã cập nhật tài khoản admin.');
    }

    public function disable(Request $request, User $user, AuditLogService $audit): RedirectResponse
    {
        $this->ensureAdminUser($user);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['status' => 'Không thể khóa tài khoản đang đăng nhập.']);
        }

        if ($user->status === 'disabled') {
            return redirect()
                ->route('admin.users.index')
                ->with('status', 'Tài khoản đã bị khóa.');
        }

        $before = $user->status;
        $user->update(['status' => 'disabled']);

        $audit->record('admin.users.update', $request->user(), $user, [
            'email' => $user->email,
            'status_from' => $before,
            'status_to' => 'disabled',
        ], $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Đã khóa tài khoản admin.');
    }

    private function ensureAdminUser(User $user): void
    {
        abort_unless($user->is_admin, 404);
    }
}
