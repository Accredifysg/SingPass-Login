<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

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

        try {
            $singPassLogin->handleCallback($code, $state);
        } catch (SingPassLoginException $e) {
            return $e->render();
        }

        return redirect()->intended();
    }
}
