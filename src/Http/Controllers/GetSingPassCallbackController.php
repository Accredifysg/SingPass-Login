<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\SingPassAuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\SingPassGetEndpointException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassLoginInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GetSingPassCallbackController extends Controller
{
    /**
     * Handles the callback from SingPass
     */
    public function __invoke(
        Request $request,
        SingPassLoginInterface $singPassLogin,
        DPoPServiceInterface $dpopService,
    ): RedirectResponse {
        try {
            // Handle authentication error responses per OIDC spec
            if ($request->has('error')) {
                throw new SingPassAuthenticationErrorException(
                    errorCode: $request->input('error'),
                    errorDescription: $request->input('error_description'),
                );
            }

            $code = $request->input('code');
            $state = $request->input('state');

            if (! $code || ! $state || ! is_string($state)) {
                throw new SingPassGetEndpointException;
            }

            // Verify state matches a pending auth request (CSRF protection)
            if (! session()->pull("auth_state_{$state}")) {
                throw new SingPassGetEndpointException;
            }

            // Retrieve auth context from session
            $dpopKey = $dpopService->retrieveKeyForState($state);
            $codeVerifier = session()->get("code_verifier_{$state}");
            $clientId = session()->get("auth_client_id_{$state}");
            $redirectUri = session()->get("auth_redirect_uri_{$state}");

            if (! $dpopKey || ! $codeVerifier || ! $clientId || ! $redirectUri) {
                throw new SingPassGetEndpointException;
            }

            $singPassLogin->handleCallback($code, $state, $codeVerifier, $dpopKey, $clientId, $redirectUri);

            // Clean up session data
            $dpopService->clearKeyForState($state);
            session()->forget("code_verifier_{$state}");
            session()->forget("auth_client_id_{$state}");
            session()->forget("auth_redirect_uri_{$state}");
        } catch (SingPassLoginException|SingPassGetEndpointException|SingPassAuthenticationErrorException $e) {
            return $e->render();
        }

        return redirect()->intended();
    }
}
