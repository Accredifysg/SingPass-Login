<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\DTOs;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
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
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'uinfin']);
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
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.available_scopes', ['openid', 'uinfin']);
        Config::set('singpass-login.login_scopes', ['openid']);

        $config = ProviderConfig::singPassMyInfo();

        $this->assertEquals('https://example.com/discovery', $config->discoveryEndpoint);
        $this->assertEquals('myinfo-client-id', $config->clientId);
        $this->assertEquals('https://example.com/myinfo-callback', $config->redirectUri);
        $this->assertEquals('https://example.com', $config->domain);
        $this->assertEquals('openId:myinfo', $config->cacheKey);
        $this->assertEquals(['openid', 'uinfin'], $config->availableScopes);
        $this->assertEquals(['openid'], $config->loginScopes);
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
}
