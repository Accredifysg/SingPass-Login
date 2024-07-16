<?php

namespace Accredifysg\SingPassLogin\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use App\Providers\RouteServiceProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class SingPassSuccessfulLoginListener
{
    public function handle(SingPassSuccessfulLoginEvent $event)
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->getNric();

        $user = User::where('nric', '=', $nric)->first();

        if (! $user) {
            throw new ModelNotFoundException();
        }

        Auth::login($user);

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
