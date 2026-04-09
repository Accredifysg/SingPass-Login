<?php

namespace Accredifysg\SingPassLogin\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoginCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        FapiCallbackService $fapiCallback,
    ): RedirectResponse {
        $session = null;

        try {
            $session = $fapiCallback->validateAndRetrieveSession($request);
            $config = ProviderConfig::singPassLogin();
            $result = $fapiCallback->processCallback($session, $config);

            $singPassUser = SingPassUser::fromPayload($result->idTokenPayload ?? []);
            event(new SingPassSuccessfulLoginEvent($singPassUser, $session->state));
        } catch (SingPassLoginException|AuthFlowException|AuthenticationErrorException $e) {
            return $e->render();
        } finally {
            if ($session) {
                $fapiCallback->cleanupSession($session->state);
            }
        }

        return redirect()->intended();
    }
}
