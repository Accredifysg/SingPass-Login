<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Services\ScopeValidationService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ScopeValidationServiceTest extends TestCase
{
    private ScopeValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScopeValidationService;
    }

    public function test_it_parses_and_validates_string_scopes(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $result = $this->service->parseAndValidate('openid,name,email');

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_parses_and_validates_array_scopes(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $result = $this->service->parseAndValidate(['openid', 'name', 'email']);

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_adds_openid_when_not_present(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $result = $this->service->parseAndValidate('name,email');

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_places_openid_first_when_added(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $result = $this->service->parseAndValidate('name,email');

        $this->assertEquals('openid', $result[0]);
    }

    public function test_it_does_not_duplicate_openid(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $result = $this->service->parseAndValidate('openid,name,email');

        $openidCount = count(array_filter($result, fn ($scope) => $scope === 'openid'));
        $this->assertEquals(1, $openidCount);
    }

    public function test_it_throws_exception_for_invalid_scope(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid scope requested: 'invalid_scope'");

        $this->service->parseAndValidate('openid,name,invalid_scope');
    }

    public function test_it_shows_available_scopes_in_exception(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available scopes: openid, name, email');

        $this->service->parseAndValidate('invalid');
    }

    public function test_it_allows_openid_even_when_not_in_config(): void
    {
        Config::set('singpass-login.available_scopes', ['name', 'email', 'mobileno']);

        $result = $this->service->parseAndValidate('openid,name,email');

        $this->assertContains('openid', $result);
    }

    public function test_it_formats_scopes_for_oauth_with_spaces(): void
    {
        $scopes = ['openid', 'name', 'email'];

        $result = $this->service->formatForOAuth($scopes);

        $this->assertEquals('openid name email', $result);
    }

    public function test_it_handles_single_scope(): void
    {
        Config::set('singpass-login.available_scopes', ['openid']);

        $result = $this->service->parseAndValidate('openid');

        $this->assertEquals(['openid'], $result);
    }

    public function test_it_handles_empty_available_scopes_config(): void
    {
        Config::set('singpass-login.available_scopes', []);

        // Should still allow openid even with empty config
        $result = $this->service->parseAndValidate('openid');

        $this->assertEquals(['openid'], $result);
    }

    public function test_it_validates_each_scope_in_array(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid scope requested: 'email'");

        $this->service->parseAndValidate(['openid', 'name', 'email']);
    }

    public function test_full_workflow_parse_validate_format(): void
    {
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email', 'mobileno']);

        // Parse and validate
        $validated = $this->service->parseAndValidate('name,email');

        // Should have openid prepended
        $this->assertContains('openid', $validated);
        $this->assertContains('name', $validated);
        $this->assertContains('email', $validated);

        // Format for OAuth
        $formatted = $this->service->formatForOAuth($validated);

        // Should be space-separated
        $this->assertEquals('openid name email', $formatted);
    }
}
