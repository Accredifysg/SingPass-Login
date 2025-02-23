<?php

namespace Accredifysg\SingPassLogin\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

class SingPassSuccessfulLoginListener
{
    public function handle(SingPassSuccessfulLoginEvent $event): void
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->getNric();

        $user = User::where('nric', '=', $nric)->first();

        if (! $user) {
            throw new SingPassLoginException;
        }

        if (str_starts_with($event->getState(), 'ENABLE')) {
            $user->update(['nric' => $nric]);
        }

        Auth::login($user);
    }
}
