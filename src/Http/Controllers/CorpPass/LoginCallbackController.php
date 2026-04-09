<?php

namespace Accredifysg\SingPassLogin\Http\Controllers\CorpPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Events\CorpPassDataRetrievedEvent;
use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Models\CorpPassUser;
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
            $config = ProviderConfig::corpPass();
            $result = $fapiCallback->processCallback($session, $config);

            if ($result->userInfoData !== null) {
                event(new CorpPassDataRetrievedEvent($result->userInfoData, $session->state));
            }

            if ($result->idTokenPayload !== null) {
                $corpPassUser = CorpPassUser::fromPayload($result->idTokenPayload);
                event(new CorpPassSuccessfulLoginEvent($corpPassUser, $session->state));
            }
        } catch (JwtPayloadException) {
            return (new AuthFlowException(400, 'Invalid identity token payload'))->render();
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
