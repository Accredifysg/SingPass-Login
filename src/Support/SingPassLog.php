<?php

namespace Accredifysg\SingPassLogin\Support;

use Illuminate\Support\Facades\Log;

final class SingPassLog
{
    private const REDACTED_PARAMS = [
        'client_assertion',
        'code_verifier',
        'id_token',
        'access_token',
    ];

    public static function enabled(): bool
    {
        return (bool) config('ndi.enable_logging', false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        if (self::enabled()) {
            Log::info("[SingPass] {$message}", $context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        if (self::enabled()) {
            Log::error("[SingPass] {$message}", $context);
        }
    }

    /**
     * Redact sensitive values from a parameter array for safe logging.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function redact(array $params): array
    {
        foreach (self::REDACTED_PARAMS as $key) {
            if (isset($params[$key])) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }
}
