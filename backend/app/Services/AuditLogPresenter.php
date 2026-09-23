<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassForm;
use App\Models\Form;
use App\Models\MiniAppUser;
use App\Models\PaperUploadBatch;
use App\Models\Response;
use App\Models\Teacher;
use App\Models\User;

class AuditLogPresenter
{
    public const CATEGORY_LOGIN = 'login';

    public const CATEGORY_VOTE = 'vote';

    public const CATEGORY_PAPER = 'paper';

    public const CATEGORY_CLASS_FORM = 'class_form';

    public const CATEGORY_ADMIN = 'admin';

    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
        self::CATEGORY_LOGIN => 'Đăng nhập',
        self::CATEGORY_VOTE => 'Phiếu vote',
        self::CATEGORY_PAPER => 'Giấy',
        self::CATEGORY_CLASS_FORM => 'Lớp/Form',
        self::CATEGORY_ADMIN => 'Quản trị',
    ];

    /** @var array<string, string> */
    private const ACTION_CATEGORY = [
        'admin.login' => self::CATEGORY_LOGIN,
        'admin.logout' => self::CATEGORY_LOGIN,
        'teacher.login' => self::CATEGORY_LOGIN,
        'teacher.login_failed' => self::CATEGORY_LOGIN,
        'teacher.auto_created' => self::CATEGORY_LOGIN,
        'teacher.logout' => self::CATEGORY_LOGIN,
        'miniapp.auth' => self::CATEGORY_LOGIN,
        'vote.parent_create' => self::CATEGORY_VOTE,
        'vote.parent_change' => self::CATEGORY_VOTE,
        'paper.upload' => self::CATEGORY_PAPER,
        'paper.confirm' => self::CATEGORY_PAPER,
        'class_form.ensure_link' => self::CATEGORY_CLASS_FORM,
        'class_form.teacher_note' => self::CATEGORY_CLASS_FORM,
        'class_form.close' => self::CATEGORY_CLASS_FORM,
        'class_form.reopen' => self::CATEGORY_CLASS_FORM,
        'admin.export_csv' => self::CATEGORY_ADMIN,
        'admin.schools.import' => self::CATEGORY_ADMIN,
        'admin.users.create' => self::CATEGORY_ADMIN,
        'admin.users.update' => self::CATEGORY_ADMIN,
        'admin.teacher.update' => self::CATEGORY_ADMIN,
    ];

    /** @var array<string, string> */
    private const CHOICE_LABELS = [
        'agree' => 'Đồng ý',
        'disagree' => 'Không đồng ý',
        'yes' => 'Có',
        'no' => 'Không',
    ];

    public function describe(AuditLog $log): string
    {
        $actor = $this->actorLabel($log);
        $payload = $log->payload ?? [];

        return match ($log->action) {
            'admin.login' => "{$actor} đã đăng nhập vào trang quản trị.",
            'admin.logout' => "{$actor} đã đăng xuất khỏi trang quản trị.",
            'admin.export_csv' => "{$actor} đã tải file Excel/CSV kết quả form 「{$this->formTitle($log)}」.",
            'admin.schools.import' => sprintf(
                '%s đã import danh sách trường (%d thêm, %d cập nhật).',
                $actor,
                (int) ($payload['added'] ?? $payload['created'] ?? 0),
                (int) ($payload['updated'] ?? 0),
            ),
            'admin.users.create' => sprintf(
                '%s đã tạo tài khoản admin %s.',
                $actor,
                $payload['email'] ?? $this->entityLabel($log),
            ),
            'admin.users.update' => sprintf(
                '%s đã cập nhật tài khoản admin %s.',
                $actor,
                $payload['email'] ?? $this->entityLabel($log),
            ),
            'admin.teacher.update' => sprintf(
                '%s đã cập nhật trạng thái giáo viên %s (%s → %s).',
                $actor,
                $this->entityLabel($log),
                $payload['status_from'] ?? '—',
                $payload['status_to'] ?? '—',
            ),
            'teacher.login' => sprintf(
                'Giáo viên %s đã đăng nhập (%s).',
                $actor,
                $this->viaLabel($payload['via'] ?? 'web'),
            ),
            'teacher.login_failed' => sprintf(
                'Đăng nhập giáo viên thất bại — Zalo ID %s: tài khoản bị khóa hoặc chưa đăng ký.',
                $payload['zalo_id'] ?? $payload['phone'] ?? '—',
            ),
            'teacher.auto_created' => "Giáo viên {$actor} đã đăng ký lần đầu qua Zalo.",
            'teacher.logout' => "Giáo viên {$actor} đã đăng xuất.",
            'miniapp.auth' => sprintf(
                'Người dùng Mini App %s đăng nhập với vai trò %s.',
                $payload['name'] ?? $this->actorLabel($log),
                $this->roleLabel($payload['role'] ?? 'parent'),
            ),
            'vote.parent_create' => sprintf(
                'Phụ huynh đã gửi phiếu %s cho lớp %s (form %s).',
                $this->choiceLabel($log, $payload),
                $this->classLabelFromLog($log),
                $this->formTitle($log),
            ),
            'vote.parent_change' => sprintf(
                'Phụ huynh đã đổi lựa chọn thành %s (lớp %s).',
                $this->choiceLabel($log, $payload),
                $this->classLabelFromLog($log),
            ),
            'class_form.ensure_link' => sprintf(
                'Giáo viên %s đã tạo / mở link vote form %s cho lớp %s.',
                $actor,
                $this->formTitle($log),
                $this->classLabelFromLog($log),
            ),
            'class_form.teacher_note' => sprintf(
                'Giáo viên %s đã thêm ghi chú / file đính kèm cho lớp %s.',
                $actor,
                $this->classLabelFromLog($log),
            ),
            'class_form.close' => sprintf(
                'Giáo viên %s đã đóng thu phiếu lớp %s.',
                $actor,
                $this->classLabelFromLog($log),
            ),
            'class_form.reopen' => sprintf(
                'Giáo viên %s đã mở lại thu phiếu lớp %s.',
                $actor,
                $this->classLabelFromLog($log),
            ),
            'paper.upload' => sprintf(
                'Giáo viên %s đã upload %d ảnh phiếu giấy (lớp %s).',
                $actor,
                (int) ($payload['images'] ?? $payload['count'] ?? 0),
                $this->classLabelFromLog($log),
            ),
            'paper.confirm' => sprintf(
                'Giáo viên %s đã xác nhận %d Đồng ý / %d Không từ phiếu giấy.',
                $actor,
                ...$this->paperConfirmCounts($log, $payload),
            ),
            default => sprintf(
                '%s — %s',
                $log->action,
                $this->entityLabel($log) !== '—' ? $this->entityLabel($log) : $actor,
            ),
        };
    }

    public function summary(AuditLog $log): string
    {
        $text = $this->describe($log);

        return mb_strlen($text) > 88 ? mb_substr($text, 0, 85).'…' : $text;
    }

    public function category(string $action): string
    {
        return self::ACTION_CATEGORY[$action] ?? self::CATEGORY_ADMIN;
    }

    public function categoryLabel(string $action): string
    {
        return self::CATEGORY_LABELS[$this->category($action)] ?? 'Quản trị';
    }

    /**
     * @return array<string, string>
     */
    public function actionOptions(): array
    {
        return [
            '' => '— Tất cả —',
            ...self::CATEGORY_LABELS,
        ];
    }

    /**
     * @return list<string>
     */
    public function actionsForCategory(string $category): array
    {
        if ($category === '') {
            return [];
        }

        return array_keys(array_filter(
            self::ACTION_CATEGORY,
            fn (string $cat): bool => $cat === $category,
        ));
    }

    public function actorLabel(AuditLog $log): string
    {
        $actor = $log->actor;

        if ($actor instanceof User) {
            return $actor->email ?? $actor->name ?? 'Admin';
        }

        if ($actor instanceof Teacher) {
            return $this->teacherName($actor);
        }

        if ($actor instanceof MiniAppUser) {
            return $actor->name ?? $actor->phone ?? ('Zalo '.$actor->zalo_user_id);
        }

        $payload = $log->payload ?? [];

        if ($log->action === 'teacher.login_failed') {
            return $payload['zalo_id'] ?? $payload['phone'] ?? 'Không xác định';
        }

        if ($log->actor_type && $log->actor_id) {
            return class_basename((string) $log->actor_type).' #'.$log->actor_id;
        }

        return 'Hệ thống';
    }

    public function entityLabel(AuditLog $log): string
    {
        $entity = $log->entity;

        if ($entity instanceof Form) {
            return $entity->title ?? 'Form #'.$entity->id;
        }

        if ($entity instanceof ClassForm) {
            return $this->classFormLabel($entity);
        }

        if ($entity instanceof Response) {
            $entity->loadMissing('classForm.classProfile.school', 'classForm.form');

            return $this->classFormLabel($entity->classForm);
        }

        if ($entity instanceof Teacher) {
            return $this->teacherName($entity);
        }

        if ($entity instanceof PaperUploadBatch) {
            $entity->loadMissing('classForm.classProfile.school');

            return 'Batch #'.$entity->id.' · '.$this->classFormLabel($entity->classForm);
        }

        if ($log->entity_type && $log->entity_id) {
            return class_basename((string) $log->entity_type).' #'.$log->entity_id.' (đã xóa)';
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function choiceLabel(AuditLog $log, array $payload): string
    {
        $choice = (string) ($payload['choice'] ?? '');

        if ($choice === '') {
            return '—';
        }

        return self::CHOICE_LABELS[$choice] ?? $choice;
    }

    private function formTitle(AuditLog $log): string
    {
        $entity = $log->entity;

        if ($entity instanceof Form) {
            return $entity->title ?? 'Form #'.$entity->id;
        }

        if ($entity instanceof ClassForm) {
            $entity->loadMissing('form');

            return $entity->form?->title ?? 'Form #'.$entity->form_id;
        }

        if ($entity instanceof Response) {
            $entity->loadMissing('classForm.form');

            return $entity->classForm?->form?->title ?? '—';
        }

        $payload = $log->payload ?? [];
        if (! empty($payload['form_id'])) {
            return 'Form #'.$payload['form_id'];
        }

        return '—';
    }

    private function classLabelFromLog(AuditLog $log): string
    {
        $entity = $log->entity;

        if ($entity instanceof ClassForm) {
            return $this->classFormLabel($entity);
        }

        if ($entity instanceof Response) {
            $entity->loadMissing('classForm.classProfile.school');

            return $this->classFormLabel($entity->classForm);
        }

        if ($entity instanceof PaperUploadBatch) {
            $entity->loadMissing('classForm.classProfile.school');

            return $this->classFormLabel($entity->classForm);
        }

        $payload = $log->payload ?? [];
        if (! empty($payload['class_form_id'])) {
            $classForm = ClassForm::query()
                ->with('classProfile.school')
                ->find($payload['class_form_id']);

            if ($classForm) {
                return $this->classFormLabel($classForm);
            }

            return 'Lớp #'.$payload['class_form_id'].' (đã xóa)';
        }

        return '—';
    }

    private function classFormLabel(?ClassForm $classForm): string
    {
        if (! $classForm) {
            return '—';
        }

        $classForm->loadMissing('classProfile.school');

        $className = $classForm->classProfile?->class_name ?? '—';
        $schoolName = $classForm->classProfile?->school?->name ?? '—';

        return "{$className} — {$schoolName}";
    }

    private function teacherName(Teacher $teacher): string
    {
        return $teacher->name ?: ($teacher->phone ?: ('GV #'.$teacher->id));
    }

    private function viaLabel(string $via): string
    {
        return match ($via) {
            'oauth' => 'Zalo OAuth',
            'dev_login' => 'dev login',
            'miniapp' => 'Mini App',
            default => $via,
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'teacher' => 'Giáo viên',
            'parent' => 'Phụ huynh',
            default => $role,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: int}
     */
    private function paperConfirmCounts(AuditLog $log, array $payload): array
    {
        $entity = $log->entity;

        if ($entity instanceof PaperUploadBatch) {
            $entity->loadMissing('items');
            $agree = $entity->items->where('confirmed_choice', 'agree')->count();
            $disagree = $entity->items->where('confirmed_choice', 'disagree')->count();

            if ($agree > 0 || $disagree > 0) {
                return [$agree, $disagree];
            }
        }

        if (isset($payload['agree'], $payload['disagree'])) {
            return [(int) $payload['agree'], (int) $payload['disagree']];
        }

        $confirmed = (int) ($payload['confirmed'] ?? 0);

        return [$confirmed, 0];
    }
}
