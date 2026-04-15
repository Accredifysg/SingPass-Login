<?php

namespace Accredifysg\SingPassLogin\Listeners;

use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\CorpPassLoginException;
use Accredifysg\SingPassLogin\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class CorpPassSuccessfulLoginListener
{
    public function handle(CorpPassSuccessfulLoginEvent $event): void
    {
        $corpPassUser = $event->getCorpPassUser();
        $entityId = $corpPassUser->getEntityId();
        $nric = $corpPassUser->getIdentityNumber();

        if ($nric === null || $nric === '') {
            throw new CorpPassLoginException;
        }

        $user = User::query()
            ->where('corppass_entity_id', '=', $entityId)
            ->where('nric', '=', $nric)
            ->first();

        if (! $user) {
            throw new CorpPassLoginException;
        }

        /** @var Authenticatable $user */
        Auth::login($user);
    }
}
