<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\SingPassGetEndpointException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Interfaces\SingPassLoginInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GetSingPassCallbackController extends Controller
{
    /**
     * Handles the callback from SingPass
     */
    public function __invoke(Request $request, SingPassLoginInterface $singPassLogin): RedirectResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $codeVerifier = $request->cookie('code_verifier');

        try {
            if (! $code || ! $state || ! $codeVerifier || ! is_string($codeVerifier)) {
                throw new SingPassGetEndpointException;
            }

            $singPassLogin->handleCallback($code, $state, $codeVerifier);
        } catch (SingPassLoginException|SingPassGetEndpointException $e) {
            return $e->render();
        }

        return redirect()->intended();
    }
}
