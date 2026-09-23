<?php

namespace App\Http\Controllers\Api\Miniapp;

use App\Http\Controllers\Controller;
use App\Models\MiniAppUser;
use App\Services\AuditLogService;
use App\Services\TeacherProvisionService;
use App\Services\ZaloAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthController extends Controller
{
    public function store(
        Request $request,
        ZaloAuthService $zalo,
        AuditLogService $audit,
        TeacherProvisionService $provision,
    ): JsonResponse {
        $data = $request->validate([
            'access_token' => ['nullable', 'string', 'max:2048'],
            'zalo_user_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'dev' => ['sometimes', 'boolean'],
            'register_as' => ['nullable', 'string', 'in:teacher,parent'],
        ]);

        try {
            if (! empty($data['dev']) || (config('services.zalo.dev_login') && empty($data['access_token']) && ! empty($data['zalo_user_id']))) {
                if (! config('services.zalo.dev_login')) {
                    return response()->json(['message' => 'Dev auth bị tắt.'], 403);
                }
                $profile = [
                    'id' => (string) $data['zalo_user_id'],
                    'name' => 'Dev '.$data['zalo_user_id'],
                ];
            } else {
                $token = $data['access_token'] ?? '';
                if ($token === '') {
                    return response()->json(['message' => 'Thiếu access_token.'], 422);
                }
                $profile = $zalo->fetchProfile($token);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $registerAs = $data['register_as'] ?? null;
        $wantsTeacher = $registerAs === 'teacher';

        // Identity is Zalo id only — never bind teacher by client-supplied phone.
        $teacher = $provision->findByZaloId($profile['id']);

        if ($provision->isLocked($teacher)) {
            $audit->record('teacher.login_failed', null, $teacher, [
                'zalo_id' => $profile['id'],
                'via' => 'miniapp',
                'reason' => 'disabled',
            ], $request);

            return response()->json(['message' => TeacherProvisionService::LOCKED_MESSAGE], 403);
        }

        if (! $teacher && $wantsTeacher) {
            ['teacher' => $teacher] = $provision->findOrCreateActive(
                $profile['id'],
                $profile['name'] ?? null,
                null,
                $audit,
                $request,
                'miniapp',
            );
        } elseif ($teacher && $profile['name'] && ! $teacher->name) {
            $teacher->update(['name' => $profile['name']]);
        }

        $role = ($teacher && $teacher->isActive()) ? 'teacher' : 'parent';

        $user = MiniAppUser::query()->updateOrCreate(
            ['zalo_user_id' => $profile['id']],
            [
                'phone' => $data['phone'] ?? null,
                'name' => $profile['name'] ?? null,
                'role' => $role,
                'teacher_id' => $teacher?->id,
            ]
        );

        $user->tokens()->delete();
        $plain = $user->createToken('miniapp')->plainTextToken;

        $audit->record('miniapp.auth', $user, $teacher, [
            'role' => $role,
            'zalo_user_id' => $profile['id'],
            'register_as' => $registerAs,
        ], $request);

        return response()->json([
            'token' => $plain,
            'user' => [
                'zalo_user_id' => $user->zalo_user_id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'teacher_id' => $user->teacher_id,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var MiniAppUser $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'zalo_user_id' => $user->zalo_user_id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'teacher_id' => $user->teacher_id,
            ],
        ]);
    }
}
