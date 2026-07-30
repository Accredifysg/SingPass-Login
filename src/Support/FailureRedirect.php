<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Support;

use Illuminate\Http\RedirectResponse;

final class FailureRedirect
{
    /**
     * Build the redirect response for a failed login/callback.
     *
     * When `ndi.failure_redirect_url` is configured, the browser is sent there
     * with `error` and `error_description` query parameters — suitable for a
     * frontend on another origin, which cannot read session-flashed errors.
     *
     * When it is not configured, this falls back to the legacy behaviour:
     * `redirect()->route('login')->withErrors($errors)`, which requires the
     * host app to define a GET route named `login`.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function make(array $errors, string $errorCode, ?string $errorDescription = null): RedirectResponse
    {
        $url = config('ndi.failure_redirect_url');

        if (is_string($url) && $url !== '') {
            $query = http_build_query(array_filter(
                [
                    'error' => $errorCode,
                    'error_description' => $errorDescription,
                ],
                static fn (?string $value): bool => $value !== null && $value !== ''
            ));

            $separator = str_contains($url, '?') ? '&' : '?';

            return redirect()->away($url.$separator.$query);
        }

        return redirect()->route('login')->withErrors($errors);
    }
}
