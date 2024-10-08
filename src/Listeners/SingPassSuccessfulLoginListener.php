<?php

namespace Accredifysg\SingPassLogin\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SingPassSuccessfulLoginListener
{
    public function handle(SingPassSuccessfulLoginEvent $event): RedirectResponse
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->getNric();

        $user = User::where('nric', '=', $nric)->first();

        if (! $user) {
            throw new ModelNotFoundException;
        }

        Auth::login($user);

        return redirect()->intended();
    }
}
