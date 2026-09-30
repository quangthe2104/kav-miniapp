<?php

namespace App\Http\Controllers\Api\Miniapp;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\MiniAppUser;
use App\Models\Response;
use App\Services\AuditLogService;
use App\Services\ClassFormLinkService;
use App\Services\CoverageService;
use App\Services\ZaloAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class VoteController extends Controller
{
    public function show(Request $request, string $token, ClassFormLinkService $links, CoverageService $coverage): JsonResponse
    {
        $classForm = $links->findByPlainToken($token);
        if (! $classForm) {
            return response()->json(['message' => 'Link không hợp lệ.'], 404);
        }

        $classForm->load(['form', 'classProfile.school']);
        $stats = $coverage->stats($classForm);
        $options = $classForm->form->resolvedOptions();
        $quota = max(1, (int) $classForm->classProfile->quota);
        $remaining = max(0, $quota - (int) $stats['coverage']);
        $choiceValues = array_map(fn ($c) => (string) $c['value'], $stats['choice_counts']);
        $colors = $classForm->form->choiceChartColors($choiceValues);

        $choiceCounts = [];
        foreach ($stats['choice_counts'] as $i => $c) {
            $choiceCounts[] = [
                'value' => (string) $c['value'],
                'label' => (string) $c['label'],
                'count' => (int) $c['count'],
                'color' => $colors[$i] ?? '#94a3b8',
            ];
        }

        $payload = [
            'form' => [
                'title' => $classForm->form->title,
                'content' => $classForm->form->content,
                'options' => $options,
            ],
            'class' => [
                'name' => $classForm->classProfile->class_name,
                'school' => $classForm->classProfile->school?->name,
                'quota' => $classForm->classProfile->quota,
            ],
            'status' => $classForm->status,
            'coverage' => $stats['coverage'],
            'stats' => [
                'coverage' => $stats['coverage'],
                'agree' => $stats['agree'],
                'disagree' => $stats['disagree'],
                'total' => $stats['total'],
                'remaining' => $remaining,
                'remaining_color' => Form::remainingChartColor(),
                'choice_counts' => $choiceCounts,
            ],
            'form_active' => $classForm->form->isActive() && $classForm->status !== 'closed',
            'accepting_new' => $coverage->isAcceptingNewVotes($classForm) && $classForm->status === 'open',
            'can_change' => $coverage->canParentChangeVote($classForm),
        ];

        $user = $this->optionalMiniAppUser($request);
        if ($user) {
            $existing = Response::query()
                ->where('class_form_id', $classForm->id)
                ->where('zalo_user_id', $user->zalo_user_id)
                ->where('channel', 'zalo')
                ->where('status', 'valid')
                ->first();

            if ($existing) {
                $payload['my_choice'] = $existing->choice;
            }
        }

        return response()->json($payload);
    }

    private function optionalMiniAppUser(Request $request): ?MiniAppUser
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof MiniAppUser ? $tokenable : null;
    }

    public function store(
        Request $request,
        string $token,
        ClassFormLinkService $links,
        CoverageService $coverage,
        AuditLogService $audit,
        ZaloAuthService $zalo,
    ): JsonResponse {
        $classForm = $links->findByPlainToken($token);
        if (! $classForm) {
            return response()->json(['message' => 'Link không hợp lệ.'], 404);
        }
        $classForm->load('form');

        if (! $classForm->form->isActive() || $classForm->status === 'closed') {
            return response()->json(['message' => 'Form đã hết hạn hoặc đã đóng.'], 422);
        }

        $allowedChoices = $classForm->form->allowedChoiceValues();

        $data = $request->validate([
            'choice' => ['required', 'string', 'max:32', Rule::in($allowedChoices)],
            'access_token' => ['nullable', 'string', 'max:2048'],
            'phone_token' => ['nullable', 'string', 'max:2048'],
            'display_name' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var MiniAppUser $user */
        $user = $request->user();
        $this->syncZaloProfile($request, $user, $data, $zalo, $audit);
        $phone = $user->phone;

        $existing = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('zalo_user_id', $user->zalo_user_id)
            ->where('channel', 'zalo')
            ->where('status', 'valid')
            ->first();

        try {
            if ($existing) {
                $coverage->updateParentChoice(
                    $classForm,
                    $user->zalo_user_id,
                    $data['choice'],
                    $phone
                );
                $audit->record('vote.parent_change', $user, $classForm, [
                    'via' => 'miniapp',
                    'choice' => $data['choice'],
                ], $request);

                return response()->json(['message' => 'Đã cập nhật lựa chọn.', 'updated' => true]);
            }

            $response = $coverage->createResponseAtomic($classForm, [
                'channel' => 'zalo',
                'choice' => $data['choice'],
                'zalo_user_id' => $user->zalo_user_id,
                'phone' => $phone,
                'created_by_type' => MiniAppUser::class,
                'created_by_id' => $user->id,
            ], 1);

            $audit->record('vote.parent_create', $user, $response, [
                'via' => 'miniapp',
                'class_form_id' => $classForm->id,
            ], $request);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã ghi nhận phiếu.', 'updated' => false]);
    }

    /**
     * Name/phone are best-effort: a parent who declines Zalo permissions can still vote.
     *
     * @param  array{access_token?: ?string, phone_token?: ?string, display_name?: ?string}  $data
     */
    private function syncZaloProfile(Request $request, MiniAppUser $user, array $data, ZaloAuthService $zalo, AuditLogService $audit): void
    {
        $accessToken = (string) ($data['access_token'] ?? '');
        if ($accessToken === '') {
            return;
        }

        try {
            $profile = $zalo->fetchProfile($accessToken);
        } catch (RuntimeException $e) {
            Log::warning('miniapp.vote profile sync failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return;
        }

        // The token must belong to the signed-in parent, otherwise another user's name/phone could be attached.
        if ($profile['id'] !== $user->zalo_user_id) {
            Log::warning('miniapp.vote access_token user mismatch', ['user_id' => $user->id]);

            return;
        }

        $updates = [];
        $name = $profile['name'] ?: trim((string) ($data['display_name'] ?? ''));
        if ($name !== '' && $name !== $user->name) {
            $updates['name'] = $name;
        }

        $phoneToken = (string) ($data['phone_token'] ?? '');
        if ($phoneToken !== '') {
            try {
                $phone = $zalo->fetchPhoneNumber($accessToken, $phoneToken);
                if ($phone !== $user->phone) {
                    $updates['phone'] = $phone;
                }
            } catch (RuntimeException $e) {
                Log::warning('miniapp.vote phone sync failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        if ($updates !== []) {
            $user->update($updates);
            $audit->record('miniapp.profile_sync', $user, $user, [
                'fields' => array_keys($updates),
            ], $request);
        }
    }
}
