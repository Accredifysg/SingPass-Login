<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Interfaces\SingPassLoginInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PostSingPassCallbackController extends Controller
{
    /**
     * Handles the callback from SingPass
     *
     * @param Request $request
     * @param SingPassLoginInterface $singPassLogin
     *
     * @return RedirectResponse
     */
    public function __invoke(Request $request, SingPassLoginInterface $singPassLogin): RedirectResponse
    {
        $singPassLogin->handleCallback();

        return redirect()->intended();
    }
}
