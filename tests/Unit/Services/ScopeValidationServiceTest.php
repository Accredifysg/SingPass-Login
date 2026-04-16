<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Services\ScopeValidationService;
use Accredifysg\SingPassLogin\Tests\TestCase;
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
        $result = $this->service->parseAndValidate('openid,name,email', ['openid', 'name', 'email']);

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_parses_and_validates_array_scopes(): void
    {
        $result = $this->service->parseAndValidate(['openid', 'name', 'email'], ['openid', 'name', 'email']);

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_adds_openid_when_not_present(): void
    {
        $result = $this->service->parseAndValidate('name,email', ['openid', 'name', 'email']);

        $this->assertEquals(['openid', 'name', 'email'], $result);
    }

    public function test_it_places_openid_first_when_added(): void
    {
        $result = $this->service->parseAndValidate('name,email', ['openid', 'name', 'email']);

        $this->assertEquals('openid', $result[0]);
    }

    public function test_it_does_not_duplicate_openid(): void
    {
        $result = $this->service->parseAndValidate('openid,name,email', ['openid', 'name', 'email']);

        $openidCount = count(array_filter($result, fn ($scope) => $scope === 'openid'));
        $this->assertEquals(1, $openidCount);
    }

    public function test_it_throws_exception_for_invalid_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid scope requested: 'invalid_scope'");

        $this->service->parseAndValidate('openid,name,invalid_scope', ['openid', 'name', 'email']);
    }

    public function test_it_shows_available_scopes_in_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available scopes: openid, name, email');

        $this->service->parseAndValidate('invalid', ['openid', 'name', 'email']);
    }

    public function test_it_allows_openid_even_when_not_in_config(): void
    {
        $result = $this->service->parseAndValidate('openid,name,email', ['name', 'email', 'mobileno']);

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
        $result = $this->service->parseAndValidate('openid', ['openid']);

        $this->assertEquals(['openid'], $result);
    }

    public function test_it_handles_empty_available_scopes_config(): void
    {
        $result = $this->service->parseAndValidate('openid', []);

        $this->assertEquals(['openid'], $result);
    }

    public function test_it_validates_each_scope_in_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid scope requested: 'email'");

        $this->service->parseAndValidate(['openid', 'name', 'email'], ['openid', 'name']);
    }

    public function test_full_workflow_parse_validate_format(): void
    {
        $validated = $this->service->parseAndValidate('name,email', ['openid', 'name', 'email', 'mobileno']);

        $this->assertContains('openid', $validated);
        $this->assertContains('name', $validated);
        $this->assertContains('email', $validated);

        $formatted = $this->service->formatForOAuth($validated);

        $this->assertEquals('openid name email', $formatted);
    }
}
