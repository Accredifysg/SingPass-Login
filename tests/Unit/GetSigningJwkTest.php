<?php

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Jose\Component\Core\JWK;
use Orchestra\Testbench\TestCase;

class GetSigningJwkTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // Set up default configuration values
        $app['config']->set('services.singpass-login.signing_kid', 'test-key-id');
        $app['config']->set('services.singpass-login.private_exponent', 'test-private-exponent');
    }

    public function test_get_signing_jwk_success()
    {
        // Mock the JSON file content
        $mockJwkJson = json_encode([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key-id',
                    'n' => 'test-modulus',
                    'e' => 'AQAB',
                ],
            ],
        ]);

        File::shouldReceive('get')
            ->once()
            ->with(storage_path('jwks/jwks.json'))
            ->andReturn($mockJwkJson);

        // Call the method
        $jwk = SingPassJwtService::getSigningJwk();

        // Assert the method returns a JWK object
        $this->assertInstanceOf(JWK::class, $jwk);

        // Assert the JWK object contains the expected values
        $this->assertEquals('test-key-id', $jwk->get('kid'));
        $this->assertEquals('test-modulus', $jwk->get('n'));
        $this->assertEquals('AQAB', $jwk->get('e'));
        $this->assertEquals('test-private-exponent', $jwk->get('d'));
    }

    public function test_get_signing_jwk_file_exception()
    {
        // Mock the file get method to throw an exception
        File::shouldReceive('get')
            ->once()
            ->with(storage_path('jwks/jwks.json'))
            ->andThrow(new \Exception);

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS JSON file could not be retrieved.');

        // Call the method
        SingPassJwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_invalid_json_exception()
    {
        // Mock configuration values
        Config::set('services.singpass-login.signing_kid', 'test-key-id');

        // Mock the JSON file content with invalid JSON
        File::shouldReceive('get')
            ->once()
            ->with(storage_path('jwks/jwks.json'))
            ->andReturn('invalid-json');

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS JSON Invalid.');

        // Call the method
        SingPassJwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_key_not_found_exception()
    {
        // Mock configuration values
        Config::set('services.singpass-login.signing_kid', 'non-existent-key-id');
        Config::set('services.singpass-login.private_exponent', 'test-private-exponent');

        // Mock the JSON file content
        $mockJwkJson = json_encode([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key-id',
                    'n' => 'test-modulus',
                    'e' => 'AQAB',
                ],
            ],
        ]);

        File::shouldReceive('get')
            ->once()
            ->with(storage_path('jwks/jwks.json'))
            ->andReturn($mockJwkJson);

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Signing key not found.');

        // Call the method
        SingPassJwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_private_exponent_not_set_exception()
    {
        // Mock configuration values
        Config::set('services.singpass-login.signing_kid', 'test-key-id');
        Config::set('services.singpass-login.private_exponent');

        // Mock the JSON file content
        $mockJwkJson = json_encode([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key-id',
                    'n' => 'test-modulus',
                    'e' => 'AQAB',
                ],
            ],
        ]);

        File::shouldReceive('get')
            ->once()
            ->with(storage_path('jwks/jwks.json'))
            ->andReturn($mockJwkJson);

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Private exponent not set.');

        // Call the method
        SingPassJwtService::getSigningJwk();
    }
}
