<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MyInfoCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        FapiCallbackService $fapiCallback,
    ): RedirectResponse {
        $session = null;

        try {
            $session = $fapiCallback->validateAndRetrieveSession($request);
            $config = ProviderConfig::singPassMyInfo();
            $result = $fapiCallback->processCallback($session, $config);

            if ($result->userInfoData !== null) {
                event(new MyInfoDataRetrievedEvent($result->userInfoData, $session->state));
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
