<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\SingPassLogin;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\File;

class PostSingPassCallbackController extends Controller
{
    public function __invoke(Request $request, SingPassLogin $singPassLogin)
    {
        $singPassLogin->handleCallback();
        return redirect()->intended(RouteServiceProvider::HOME);
    }
}