<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class TeacherProvisionService
{
    public const LOCKED_MESSAGE = 'Tài khoản bị khóa. Liên hệ Admin.';

    /** Max new teacher rows per IP per hour (open registration abuse cap). */
    public const AUTO_CREATE_MAX_PER_HOUR = 10;

    public function findByZaloId(string $zaloId): ?Teacher
    {
        return Teacher::query()->where('zalo_id', $zaloId)->first();
    }

    public function findByPhone(?string $phone): ?Teacher
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        return Teacher::query()->where('phone', $phone)->first();
    }

    public function isLocked(?Teacher $teacher): bool
    {
        return $teacher !== null && ! $teacher->isActive();
    }

    /**
     * Resolve or create teacher by verified Zalo id only.
     * Never match/link by client-supplied phone (account-takeover vector).
     *
     * @return array{teacher: Teacher, created: bool}
     */
    public function findOrCreateActive(
        string $zaloId,
        ?string $name,
        ?string $phone,
        AuditLogService $audit,
        ?Request $request = null,
        string $via = 'oauth',
    ): array {
        $teacher = $this->findByZaloId($zaloId);

        if ($teacher) {
            $updates = [];
            if ($name && ! $teacher->name) {
                $updates['name'] = $name;
            }
            if ($phone && ! $teacher->phone && $this->phoneAvailable($phone, $teacher->id)) {
                $updates['phone'] = $phone;
            }
            if ($updates !== []) {
                $teacher->update($updates);
            }

            return ['teacher' => $teacher->fresh(), 'created' => false];
        }

        $this->assertAutoCreateAllowed($request);

        $teacher = Teacher::query()->create([
            'zalo_id' => $zaloId,
            'name' => $name,
            'phone' => ($phone && $this->phoneAvailable($phone)) ? $phone : null,
            'status' => 'active',
        ]);

        $this->hitAutoCreate($request);

        $audit->record('teacher.auto_created', $teacher, $teacher, [
            'zalo_id' => $zaloId,
            'via' => $via,
        ], $request);

        return ['teacher' => $teacher, 'created' => true];
    }

    /**
     * Dev / web login when only phone or zalo_id is known (no Zalo profile yet).
     * Phone match is allowed only while ZALO_DEV_LOGIN is enabled (caller must gate).
     *
     * @return array{teacher: Teacher, created: bool}
     */
    public function findOrCreateFromCredentials(
        ?string $zaloId,
        ?string $phone,
        ?string $name,
        AuditLogService $audit,
        ?Request $request = null,
        string $via = 'dev_login',
    ): array {
        $teacher = null;
        if ($zaloId) {
            $teacher = $this->findByZaloId($zaloId);
        }
        if (! $teacher && $phone) {
            $teacher = $this->findByPhone($phone);
            // Link orphan row only; never steal an account that already has another zalo_id.
            if ($teacher && $zaloId && ! $teacher->zalo_id) {
                $teacher->update(['zalo_id' => $zaloId]);
            } elseif ($teacher && $zaloId && $teacher->zalo_id && $teacher->zalo_id !== $zaloId) {
                $teacher = null;
            }
        }

        if ($teacher) {
            $updates = [];
            if ($name && ! $teacher->name) {
                $updates['name'] = $name;
            }
            if ($updates !== []) {
                $teacher->update($updates);
            }

            return ['teacher' => $teacher->fresh(), 'created' => false];
        }

        $this->assertAutoCreateAllowed($request);

        $teacher = Teacher::query()->create([
            'zalo_id' => $zaloId,
            'phone' => ($phone && $this->phoneAvailable($phone)) ? $phone : null,
            'name' => $name,
            'status' => 'active',
        ]);

        $this->hitAutoCreate($request);

        $audit->record('teacher.auto_created', $teacher, $teacher, [
            'zalo_id' => $zaloId,
            'via' => $via,
            'has_phone' => filled($phone),
        ], $request);

        return ['teacher' => $teacher, 'created' => true];
    }

    private function phoneAvailable(string $phone, ?int $exceptTeacherId = null): bool
    {
        return ! Teacher::query()
            ->where('phone', $phone)
            ->when($exceptTeacherId, fn ($q) => $q->where('id', '!=', $exceptTeacherId))
            ->exists();
    }

    private function autoCreateKey(?Request $request): string
    {
        return 'teacher-auto-create:'.($request?->ip() ?: 'unknown');
    }

    private function assertAutoCreateAllowed(?Request $request): void
    {
        $key = $this->autoCreateKey($request);
        if (RateLimiter::tooManyAttempts($key, self::AUTO_CREATE_MAX_PER_HOUR)) {
            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($key),
                'Quá nhiều đăng ký giáo viên từ IP này. Thử lại sau.',
            );
        }
    }

    private function hitAutoCreate(?Request $request): void
    {
        RateLimiter::hit($this->autoCreateKey($request), 3600);
    }
}
