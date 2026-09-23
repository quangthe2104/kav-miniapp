<?php

namespace App\Http\Controllers;

use App\Models\Response;
use App\Services\AuditLogService;
use App\Services\ClassFormLinkService;
use App\Services\CoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class VoteController extends Controller
{
    public function show(string $token, ClassFormLinkService $links): RedirectResponse
    {
        $classForm = $links->findByPlainToken($token);
        abort_unless($classForm, 404);

        return redirect()->to('/miniapp/vote/'.rawurlencode($token));
    }

    public function store(Request $request, string $token, ClassFormLinkService $links, CoverageService $coverage, AuditLogService $audit): RedirectResponse
    {
        $classForm = $links->findByPlainToken($token);
        abort_unless($classForm, 404);
        $classForm->load('form');

        if (! $classForm->form->isActive() || $classForm->status === 'closed') {
            return back()->withErrors(['vote' => 'Form đã hết hạn hoặc đã đóng.']);
        }

        $allowedChoices = $classForm->form->allowedChoiceValues();

        $data = $request->validate([
            'choice' => ['required', 'string', 'max:32', Rule::in($allowedChoices)],
            'zalo_user_id' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $existing = Response::query()
            ->where('class_form_id', $classForm->id)
            ->where('zalo_user_id', $data['zalo_user_id'])
            ->where('channel', 'zalo')
            ->where('status', 'valid')
            ->first();

        try {
            if ($existing) {
                $coverage->updateParentChoice(
                    $classForm,
                    $data['zalo_user_id'],
                    $data['choice'],
                    $data['phone'] ?? null
                );

                $audit->record('vote.parent_change', null, $classForm, [
                    'zalo_user_id' => $data['zalo_user_id'],
                    'choice' => $data['choice'],
                ], $request);

                return redirect()->route('vote.show', $token)
                    ->with('status', 'Đã cập nhật lựa chọn của bạn.');
            }

            $response = $coverage->createResponseAtomic($classForm, [
                'channel' => 'zalo',
                'choice' => $data['choice'],
                'zalo_user_id' => $data['zalo_user_id'],
                'phone' => $data['phone'] ?? null,
            ], 1);

            $audit->record('vote.parent_create', null, $response, [
                'class_form_id' => $classForm->id,
                'choice' => $data['choice'],
            ], $request);
        } catch (RuntimeException $e) {
            return back()->withErrors(['vote' => $e->getMessage()])->withInput();
        }

        return redirect()->route('vote.show', $token)->with('status', 'Đã ghi nhận phiếu của bạn. Cảm ơn!');
    }
}
