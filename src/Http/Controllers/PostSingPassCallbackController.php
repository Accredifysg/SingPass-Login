<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\SingPassLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request;

class PostSingPassCallbackController extends Controller
{
    public function __invoke(Request $request, SingPassLogin $singPassLogin): RedirectResponse
    {
        $singPassLogin->handleCallback();

        return redirect()->intended();
    }
}
