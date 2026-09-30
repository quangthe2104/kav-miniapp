<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ZaloAuthService
{
    /**
     * @return array{code_verifier: string, code_challenge: string, state: string, authorize_url: string}
     */
    public function beginWebOAuth(): array
    {
        $appId = (string) config('services.zalo.app_id');
        $redirect = (string) config('services.zalo.oauth_redirect_uri');
        if ($appId === '' || $redirect === '') {
            throw new RuntimeException('Chưa cấu hình ZALO_APP_ID hoặc ZALO_WEB_REDIRECT_URI.');
        }

        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = Str::random(40);

        $authorizeUrl = 'https://oauth.zaloapp.com/v4/permission?'.http_build_query([
            'app_id' => $appId,
            'redirect_uri' => $redirect,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return [
            'code_verifier' => $verifier,
            'code_challenge' => $challenge,
            'state' => $state,
            'authorize_url' => $authorizeUrl,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token?: string|null}
     */
    public function exchangeWebCode(string $code, string $codeVerifier): array
    {
        $appId = (string) config('services.zalo.app_id');
        $secret = (string) config('services.zalo.app_secret');

        $response = Http::asForm()
            ->withHeaders(['secret_key' => $secret])
            ->post('https://oauth.zaloapp.com/v4/access_token', [
                'code' => $code,
                'app_id' => $appId,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Zalo OAuth token exchange thất bại (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Zalo không trả access_token. Kiểm tra App ID/Secret và callback URL.');
        }

        return [
            'access_token' => $token,
            'refresh_token' => $data['refresh_token'] ?? null,
        ];
    }

    /**
     * @return array{id: string, name: ?string, picture: mixed}
     */
    public function fetchProfile(string $accessToken): array
    {
        if (config('services.zalo.dev_login') && str_starts_with($accessToken, 'dev:')) {
            $id = substr($accessToken, 4);

            return [
                'id' => $id !== '' ? $id : 'dev-user',
                'name' => 'Dev User '.$id,
                'picture' => null,
            ];
        }

        $secret = (string) config('services.zalo.app_secret');
        if ($secret === '') {
            throw new RuntimeException('Chưa cấu hình ZALO_APP_SECRET.');
        }

        // Zalo requires appsecret_proof (HMAC-SHA256 of the token) since 2024-01-01.
        $response = Http::withHeaders([
            'access_token' => $accessToken,
            'appsecret_proof' => hash_hmac('sha256', $accessToken, $secret),
        ])->get('https://graph.zalo.me/v2.0/me', [
            'fields' => 'id,name,picture',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Không lấy được hồ sơ Zalo (HTTP '.$response->status().').');
        }

        $data = $response->json();
        if (! empty($data['error'])) {
            throw new RuntimeException('Zalo từ chối access token ('.$data['error'].': '.($data['message'] ?? 'unknown').').');
        }
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Zalo profile thiếu id.');
        }

        return [
            'id' => $id,
            'name' => isset($data['name']) ? (string) $data['name'] : null,
            'picture' => $data['picture'] ?? null,
        ];
    }
}
