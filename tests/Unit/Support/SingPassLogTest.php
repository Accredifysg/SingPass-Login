<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Support;

use Accredifysg\SingPassLogin\Support\SingPassLog;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class SingPassLogTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::set('ndi.enable_logging', null);
        parent::tearDown();
    }

    public function test_enabled_returns_false_when_logging_disabled(): void
    {
        Config::set('ndi.enable_logging', false);

        $this->assertFalse(SingPassLog::enabled());
    }

    public function test_enabled_returns_false_when_value_is_null(): void
    {
        Config::set('ndi.enable_logging', null);

        $this->assertFalse(SingPassLog::enabled());
    }

    public function test_enabled_returns_true_when_logging_enabled(): void
    {
        Config::set('ndi.enable_logging', true);

        $this->assertTrue(SingPassLog::enabled());
    }

    public function test_info_and_error_do_not_dispatch_when_logging_disabled(): void
    {
        Config::set('ndi.enable_logging', false);
        Event::fake([MessageLogged::class]);

        SingPassLog::info('should not log', ['a' => 1]);
        SingPassLog::error('should not log either', ['b' => 2]);

        Event::assertNotDispatched(MessageLogged::class);
    }

    public function test_info_dispatches_message_logged_when_enabled(): void
    {
        Config::set('ndi.enable_logging', true);
        Event::fake([MessageLogged::class]);

        SingPassLog::info('Scopes validated', ['scope' => 'openid']);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->level === 'info'
                && $event->message === '[SingPass] Scopes validated'
                && $event->context === ['scope' => 'openid'];
        });
    }

    public function test_error_dispatches_message_logged_when_enabled(): void
    {
        Config::set('ndi.enable_logging', true);
        Event::fake([MessageLogged::class]);

        SingPassLog::error('Token exchange failed', ['code' => 400]);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->level === 'error'
                && $event->message === '[SingPass] Token exchange failed'
                && $event->context === ['code' => 400];
        });
    }

    public function test_info_with_empty_context(): void
    {
        Config::set('ndi.enable_logging', true);
        Event::fake([MessageLogged::class]);

        SingPassLog::info('No context');

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->level === 'info'
                && $event->message === '[SingPass] No context'
                && $event->context === [];
        });
    }

    public function test_redact_replaces_all_sensitive_keys(): void
    {
        $params = [
            'client_assertion' => 'secret-jwt',
            'code_verifier' => 'verifier-secret',
            'id_token' => 'id-secret',
            'access_token' => 'at-secret',
            'client_id' => 'public-id',
        ];

        $out = SingPassLog::redact($params);

        $this->assertSame('[REDACTED]', $out['client_assertion']);
        $this->assertSame('[REDACTED]', $out['code_verifier']);
        $this->assertSame('[REDACTED]', $out['id_token']);
        $this->assertSame('[REDACTED]', $out['access_token']);
        $this->assertSame('public-id', $out['client_id']);
    }

    public function test_redact_leaves_array_unchanged_when_no_sensitive_keys(): void
    {
        $params = ['client_id' => 'x', 'redirect_uri' => 'https://example.com'];

        $this->assertSame($params, SingPassLog::redact($params));
    }

    public function test_redact_is_safe_on_empty_array(): void
    {
        $this->assertSame([], SingPassLog::redact([]));
    }
}
