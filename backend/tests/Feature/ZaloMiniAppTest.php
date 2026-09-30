<?php

namespace Tests\Feature;

use App\Services\ClassFormLinkService;
use App\Services\ZaloAuthService;
use App\Support\ParentPhone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ZaloMiniAppTest extends TestCase
{
    public function test_fetch_profile_sends_token_and_appsecret_proof_headers(): void
    {
        config(['services.zalo.app_secret' => 'secret-x', 'services.zalo.dev_login' => false]);
        Http::fake([
            'graph.zalo.me/*' => Http::response(['id' => '123', 'name' => 'Cô A']),
        ]);

        $profile = app(ZaloAuthService::class)->fetchProfile('tok-1');

        $this->assertSame('123', $profile['id']);
        $this->assertSame('Cô A', $profile['name']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('access_token', 'tok-1')
            && $r->hasHeader('appsecret_proof', hash_hmac('sha256', 'tok-1', 'secret-x'))
            && ! str_contains($r->url(), 'tok-1'));
    }

    public function test_fetch_profile_surfaces_zalo_error_payload(): void
    {
        config(['services.zalo.app_secret' => 'secret-x', 'services.zalo.dev_login' => false]);
        Http::fake([
            'graph.zalo.me/*' => Http::response(['error' => -216, 'message' => 'Access token is invalid']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('-216');

        app(ZaloAuthService::class)->fetchProfile('bad');
    }

    public function test_fetch_phone_number_exchanges_token_and_normalizes(): void
    {
        config(['services.zalo.app_secret' => 'secret-x', 'services.zalo.dev_login' => false]);
        Http::fake([
            'graph.zalo.me/v2.0/me/info' => Http::response(['data' => ['number' => '84912345678'], 'error' => 0, 'message' => 'Success']),
        ]);

        $phone = app(ZaloAuthService::class)->fetchPhoneNumber('tok-1', 'phone-tok');

        $this->assertSame('0912345678', $phone);
        Http::assertSent(fn (Request $r) => $r->hasHeader('access_token', 'tok-1')
            && $r->hasHeader('code', 'phone-tok')
            && $r->hasHeader('secret_key', 'secret-x'));
    }

    public function test_fetch_phone_number_throws_on_zalo_error(): void
    {
        config(['services.zalo.app_secret' => 'secret-x', 'services.zalo.dev_login' => false]);
        Http::fake([
            'graph.zalo.me/*' => Http::response(['error' => 119, 'message' => 'Code has been used']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('119');

        app(ZaloAuthService::class)->fetchPhoneNumber('tok-1', 'used');
    }

    public function test_parent_phone_display_full_or_masked(): void
    {
        config(['services.zalo.parent_phone_display' => 'full']);
        $this->assertSame('0912345678', ParentPhone::display('0912345678'));
        $this->assertNull(ParentPhone::display(null));

        config(['services.zalo.parent_phone_display' => 'masked']);
        $this->assertSame('•••••••678', ParentPhone::display('0912345678'));
    }

    public function test_vote_url_defaults_to_web_link(): void
    {
        config(['services.zalo.vote_link' => 'web', 'services.zalo.miniapp_id' => '999']);

        $this->assertSame(url('/miniapp/vote/abc'), app(ClassFormLinkService::class)->voteUrl('abc'));
    }

    public function test_vote_url_uses_miniapp_deep_link_with_testing_query(): void
    {
        config([
            'services.zalo.vote_link' => 'miniapp',
            'services.zalo.miniapp_id' => '999',
            'services.zalo.miniapp_link_query' => 'env=TESTING&version=3',
        ]);

        $this->assertSame(
            'https://zalo.me/s/999/vote/abc?env=TESTING&version=3',
            app(ClassFormLinkService::class)->voteUrl('abc'),
        );
    }

    public function test_vote_url_falls_back_to_web_without_miniapp_id(): void
    {
        config(['services.zalo.vote_link' => 'miniapp', 'services.zalo.miniapp_id' => '']);

        $this->assertSame(url('/miniapp/vote/abc'), app(ClassFormLinkService::class)->voteUrl('abc'));
    }
}
