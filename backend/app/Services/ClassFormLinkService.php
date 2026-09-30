<?php

namespace App\Services;

use App\Models\ClassForm;
use App\Models\ClassProfile;
use App\Models\Form;
use Illuminate\Support\Facades\DB;

class ClassFormLinkService
{
    /**
     * Tạo class_form + invite token một lần. Token giữ nguyên đến khi đóng form
     * (đóng/mở lại chỉ đổi status, không đổi link).
     *
     * @return array{class_form: ClassForm, plain_token: string}
     */
    public function ensure(ClassProfile $profile, Form $form, bool $rotateIfExists = false): array
    {
        return DB::transaction(function () use ($profile, $form, $rotateIfExists) {
            $existing = ClassForm::query()
                ->where('class_profile_id', $profile->id)
                ->where('form_id', $form->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === 'closed' && $form->isActive()) {
                    $existing->update(['status' => 'open']);
                    $existing->refresh();
                }

                if ($rotateIfExists || ! filled($existing->invite_token)) {
                    $plain = ClassForm::generatePlainToken();
                    $existing->update([
                        'invite_token' => $plain,
                        'invite_token_hash' => ClassForm::hashToken($plain),
                    ]);

                    return ['class_form' => $existing->refresh(), 'plain_token' => $plain];
                }

                return [
                    'class_form' => $existing,
                    'plain_token' => (string) $existing->invite_token,
                ];
            }

            $plain = ClassForm::generatePlainToken();
            $classForm = ClassForm::query()->create([
                'class_profile_id' => $profile->id,
                'form_id' => $form->id,
                'invite_token' => $plain,
                'invite_token_hash' => ClassForm::hashToken($plain),
                'status' => 'open',
            ]);

            return ['class_form' => $classForm, 'plain_token' => $plain];
        });
    }

    public function findByPlainToken(string $plain): ?ClassForm
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }

        return ClassForm::query()
            ->where(function ($q) use ($plain) {
                $q->where('invite_token', $plain)
                    ->orWhere('invite_token_hash', ClassForm::hashToken($plain));
            })
            ->first();
    }

    public function voteUrl(string $plainToken): string
    {
        $miniAppId = (string) config('services.zalo.miniapp_id');
        if (config('services.zalo.vote_link') === 'miniapp' && $miniAppId !== '') {
            $query = ltrim((string) config('services.zalo.miniapp_link_query'), '?');

            return 'https://zalo.me/s/'.$miniAppId.'/vote/'.rawurlencode($plainToken)
                .($query !== '' ? '?'.$query : '');
        }

        return url('/miniapp/vote/'.$plainToken);
    }

    public function voteUrlFor(ClassForm $classForm): ?string
    {
        if (! filled($classForm->invite_token)) {
            return null;
        }

        return $this->voteUrl((string) $classForm->invite_token);
    }

    /**
     * Đảm bảo có invite_token lưu DB (cho bản ghi cũ chỉ có hash).
     */
    public function ensurePersistedToken(ClassForm $classForm): string
    {
        if (filled($classForm->invite_token)) {
            return (string) $classForm->invite_token;
        }

        return DB::transaction(function () use ($classForm) {
            /** @var ClassForm $locked */
            $locked = ClassForm::query()->whereKey($classForm->id)->lockForUpdate()->firstOrFail();
            if (filled($locked->invite_token)) {
                return (string) $locked->invite_token;
            }

            $plain = ClassForm::generatePlainToken();
            $locked->update([
                'invite_token' => $plain,
                'invite_token_hash' => ClassForm::hashToken($plain),
            ]);

            return $plain;
        });
    }
}
