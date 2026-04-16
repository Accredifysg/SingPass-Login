<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\DTOs;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Exceptions\MissingConfigException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ProviderConfigTest extends TestCase
{
    public function test_singpass_login_factory(): void
    {
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', ['openid', 'user.identity']);

        $config = ProviderConfig::singPassLogin();

        $this->assertEquals('https://example.com/discovery', $config->discoveryEndpoint);
        $this->assertEquals('test-client-id', $config->clientId);
        $this->assertEquals('https://example.com/callback', $config->redirectUri);
        $this->assertEquals('https://example.com', $config->domain);
        $this->assertEquals('openId:singpass', $config->cacheKey);
        $this->assertEquals(['openid', 'user.identity'], $config->availableScopes);
        $this->assertEquals(['openid', 'user.identity'], $config->loginScopes);
    }

    public function test_singpass_myinfo_factory(): void
    {
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('myinfo.domain', 'https://example.com');
        Config::set('myinfo.available_scopes', ['openid', 'uinfin']);
        Config::set('myinfo.login_scopes', ['openid']);

        $config = ProviderConfig::singPassMyInfo();

        $this->assertEquals('https://example.com/discovery', $config->discoveryEndpoint);
        $this->assertEquals('myinfo-client-id', $config->clientId);
        $this->assertEquals('https://example.com/myinfo-callback', $config->redirectUri);
        $this->assertEquals('https://example.com', $config->domain);
        $this->assertEquals('openId:myinfo', $config->cacheKey);
        $this->assertEquals(['openid', 'uinfin'], $config->availableScopes);
        $this->assertEquals(['openid'], $config->loginScopes);
    }

    public function test_corppass_factory(): void
    {
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', ['openid', 'entity.identity', 'authinfo']);
        Config::set('corppass-login.login_scopes', ['openid', 'entity.identity']);

        $config = ProviderConfig::corpPass();

        $this->assertEquals('https://corppass.example.com/discovery', $config->discoveryEndpoint);
        $this->assertEquals('cp-client-id', $config->clientId);
        $this->assertEquals('https://example.com/cp-callback', $config->redirectUri);
        $this->assertEquals('https://corppass.example.com', $config->domain);
        $this->assertEquals('openId:corppass', $config->cacheKey);
        $this->assertEquals(['openid', 'entity.identity', 'authinfo'], $config->availableScopes);
        $this->assertEquals(['openid', 'entity.identity'], $config->loginScopes);
    }

    public function test_manual_construction(): void
    {
        $config = new ProviderConfig(
            discoveryEndpoint: 'https://custom.com/discovery',
            clientId: 'custom-id',
            redirectUri: 'https://custom.com/callback',
            domain: 'https://custom.com',
            cacheKey: 'openId:custom',
            availableScopes: ['openid'],
            loginScopes: ['openid'],
        );

        $this->assertEquals('custom-id', $config->clientId);
        $this->assertEquals('openId:custom', $config->cacheKey);
    }

    public function test_singpass_factory_throws_on_missing_client_id(): void
    {
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.client_id', null);
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.domain', 'https://example.com');

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('singpass-login.client_id');

        ProviderConfig::singPassLogin();
    }

    public function test_corppass_factory_throws_on_missing_domain(): void
    {
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.domain', null);

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('corppass-login.domain');

        ProviderConfig::corpPass();
    }

    public function test_myinfo_factory_throws_on_missing_redirect_uri(): void
    {
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', null);
        Config::set('myinfo.domain', 'https://example.com');

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('myinfo.redirect_uri');

        ProviderConfig::singPassMyInfo();
    }

    public function test_singpass_login_accepts_empty_login_scopes(): void
    {
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', []);

        $config = ProviderConfig::singPassLogin();

        $this->assertSame([], $config->availableScopes);
        $this->assertSame([], $config->loginScopes);
    }

    public function test_singpass_login_accepts_null_login_scopes(): void
    {
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', null);

        $config = ProviderConfig::singPassLogin();

        $this->assertSame([], $config->availableScopes);
        $this->assertSame([], $config->loginScopes);
    }

    public function test_corppass_throws_when_available_scopes_is_not_array(): void
    {
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', 'openid');
        Config::set('corppass-login.login_scopes', ['openid']);

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('corppass-login.available_scopes');

        ProviderConfig::corpPass();
    }

    public function test_corppass_throws_when_login_scopes_is_not_array(): void
    {
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', ['openid']);
        Config::set('corppass-login.login_scopes', true);

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('corppass-login.login_scopes');

        ProviderConfig::corpPass();
    }

    public function test_corppass_throws_when_available_scopes_contains_non_string(): void
    {
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', ['openid', 1]);
        Config::set('corppass-login.login_scopes', ['openid']);

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('corppass-login.available_scopes');

        ProviderConfig::corpPass();
    }

    public function test_myinfo_accepts_empty_scope_lists(): void
    {
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('myinfo.domain', 'https://example.com');
        Config::set('myinfo.available_scopes', []);
        Config::set('myinfo.login_scopes', null);

        $config = ProviderConfig::singPassMyInfo();

        $this->assertSame([], $config->availableScopes);
        $this->assertSame([], $config->loginScopes);
    }

    public function test_myinfo_throws_when_login_scopes_contains_non_string(): void
    {
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('myinfo.domain', 'https://example.com');
        Config::set('myinfo.available_scopes', ['openid']);
        Config::set('myinfo.login_scopes', [['nested' => 'bad']]);

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('myinfo.login_scopes');

        ProviderConfig::singPassMyInfo();
    }
}
