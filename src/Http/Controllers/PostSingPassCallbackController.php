<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\SingPassLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PostSingPassCallbackController extends Controller
{
    public function __invoke(Request $request, SingPassLogin $singPassLogin): RedirectResponse
    {
        $singPassLogin->handleCallback();

        return redirect()->intended();
    }
}
