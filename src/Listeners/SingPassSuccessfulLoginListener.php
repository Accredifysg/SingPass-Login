<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class SingPassSuccessfulLoginListener
{
    public function handle(SingPassSuccessfulLoginEvent $event): void
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->nric;

        $user = User::where('nric', '=', $nric)->first();

        if (! $user) {
            throw new SingPassLoginException;
        }

        /** @var Authenticatable $user */
        Auth::login($user);
    }
}
