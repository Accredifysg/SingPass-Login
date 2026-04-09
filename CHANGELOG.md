# Changelog

## v3.0.0

### FAPI 2.0 Compliance

The package now implements the full **FAPI 2.0** security profile as required by SingPass, MyInfo, and CorpPass:

- **Pushed Authorization Requests (PAR)**: Authorization parameters are sent via a server-to-server POST, returning a `request_uri` for the browser redirect.
- **Demonstration of Proof of Possession (DPoP)**: Ephemeral key pairs are generated per session. DPoP proof JWTs are attached to PAR, token exchange, and UserInfo requests.
- **PKCE (S256)**: Code challenge/verifier pairs secure the authorization code exchange.
- **Private-key JWT client assertions**: Client authentication uses signed JWTs with `jti` claims instead of client secrets.
- **JWE-encrypted ID tokens**: ID tokens are decrypted (JWE) and verified (JWS) before payload extraction.
- **OpenID Connect Discovery validation**: Discovery responses are validated and cached via a typed `OpenIdConfigurationDto` that enforces the presence of required endpoints.

### CorpPass Support

CorpPass FAPI 2.0 integration is now supported alongside SingPass and MyInfo:

- **`config/corppass-login.php`**: CorpPass-specific configuration (client credentials, scopes, routes, listener).
- **`ProviderConfig::corpPass()`**: Factory method for CorpPass provider configuration.
- **`CorpPassUser` model**: Hierarchical entity + actor data model reflecting CorpPass ID token structure (`sub` for entity, `act.sub` for actor).
- **`CorpPassSuccessfulLoginEvent`**: Fired on successful CorpPass login, carries a `CorpPassUser`.
- **`CorpPassDataRetrievedEvent`**: Fired when UserInfo scopes (`authinfo`, `tpauthinfo`) return authorization data.
- **Thin CorpPass controllers**: `CorpPass\LoginController` and `CorpPass\LoginCallbackController`.
- **Routes**: `GET /ndi/cp/login` and `GET /ndi/cp/callback` (configurable, toggleable).

### MyInfo Split

MyInfo has been separated into its own distinct flow with dedicated routes and controllers:

- **Dedicated routes**: `GET /ndi/mi/initiate` and `GET /ndi/mi/callback` replace the previous shared login route.
- **Separate client credentials**: `SINGPASS_MYINFO_CLIENT_ID` and `SINGPASS_MYINFO_REDIRECT_URI` with an independent OpenID discovery cache.
- **Thin controllers**: `SingPass\MyInfoController` and `SingPass\MyInfoCallbackController`.
- **`MyInfoDataRetrievedEvent`**: Fired when UserInfo data is retrieved (including empty responses).

### Architectural Refactoring

The internal architecture has been restructured around a **shared FAPI 2.0 service layer** with **thin, provider-specific controllers**:

- **`FapiAuthenticationService`**: Orchestrates PAR, DPoP, PKCE, scope validation, session storage, and authorization URL construction for any provider.
- **`FapiCallbackService`**: Orchestrates session validation, CSRF verification, token exchange, JWE/JWS processing, and UserInfo/ID token routing for any provider.
- **`ProviderConfig` DTO**: Encapsulates per-provider configuration (discovery endpoint, client ID, redirect URI, cache key, scopes). Factory methods: `singPassLogin()`, `singPassMyInfo()`, `corpPass()`.
- **`FapiCallbackResult` DTO**: Returned by `FapiCallbackService::processCallback()` with `idTokenPayload` and/or `userInfoData`.
- **`FapiSessionContext` DTO**: Holds validated session data (code, state, DPoP key, code verifier, client credentials).
- **`OpenIdConfigurationDto`**: Typed, validated DTO for OpenID discovery configuration.
- **`TokenResponseDto`**: DTO for token exchange responses with `hasAccessToken()` PHPStan assertion.
- **Thin controllers**: Each flow (SingPass Login, MyInfo, CorpPass) has lightweight controllers that delegate to the shared services and fire provider-specific events.

### Security Improvements

- **Explicit CSRF protection**: The `state` parameter is stored in the session during PAR and verified on callback. Mismatched states are rejected.
- **Session-based credential routing**: Client ID and redirect URI are stored in the session during PAR initiation, eliminating the previous reliance on user-controllable state prefixes for credential selection.
- **State prefix removal**: The `LOGIN-` / `MYINFO-` state prefixes have been removed. Credential routing is now handled securely via session storage.
- **Scope restriction for login flow**: `ProviderConfig::singPassLogin()` restricts `availableScopes` to `loginScopes`, preventing non-login scopes from being requested through the login endpoint.
- **Session cleanup in `finally` blocks**: All callback controllers clean up session data (DPoP keys, code verifiers, client credentials) in `finally` blocks, preventing stale data on exceptions.
- **Robust token endpoint error handling**: `TokenExchangeService` checks HTTP status, parses OAuth error responses (`error` / `error_description`), and validates the presence of `id_token` before returning.
- **Null-safe OpenID configuration access**: All services that retrieve `OpenIdConfigurationDto` from cache perform `instanceof` checks with service-specific exceptions.

### Route Toggles

Each flow can now be independently enabled or disabled:

| Environment Variable | Default | Controls |
|---|---|---|
| `SINGPASS_USE_DEFAULT_ROUTES` | `true` | SingPass Login routes |
| `SINGPASS_USE_DEFAULT_MYINFO_ROUTES` | `true` | MyInfo routes |
| `CORPPASS_USE_DEFAULT_ROUTES` | `true` | CorpPass routes |

The JWKS endpoint (`/ndi/jwks`) is always registered as it is shared across all providers.

### New Services

| Service | Purpose |
|---|---|
| `DPoPService` | Ephemeral key generation, DPoP proof JWT creation, key storage/retrieval |
| `PushedAuthorizationRequestService` | PAR endpoint requests with DPoP |
| `FapiAuthenticationService` | Auth initiation orchestrator (discovery, PAR, DPoP, PKCE, session) |
| `FapiCallbackService` | Callback orchestrator (session validation, token exchange, routing) |
| `ScopeValidationService` | Scope parsing and validation against provider config |

### New Exceptions

| Exception | Purpose |
|---|---|
| `AuthenticationErrorException` | OAuth error returned to callback (`error` / `error_description` query params) |
| `PushedAuthorizationRequestException` | PAR endpoint errors (includes OAuth error codes) |

---

### Breaking Changes

#### Removed Classes

| Removed | Replacement |
|---|---|
| `SingPassLogin` (main service class) | `FapiAuthenticationService` + `FapiCallbackService` |
| `SingPassLoginFacade` | Removed entirely; use dependency injection |
| `SingPassLoginInterface` | Removed entirely; use service interfaces |
| `GetAuthenticationEndpointController` | `SingPass\LoginController` + `SingPass\MyInfoController` |
| `GetSingPassCallbackController` | `SingPass\LoginCallbackController` + `SingPass\MyInfoCallbackController` |
| `GetSingPassTokenService` | `TokenExchangeService` |
| `GetSingPassJwksService` | `JwksService` |

#### Renamed Classes

| v2 Name | v3 Name |
|---|---|
| `SingPassGetEndpointException` | `AuthFlowException` |
| `SingPassAuthenticationErrorException` | `AuthenticationErrorException` |
| `SingPassTokenException` | `TokenExchangeException` |
| `SingPassJwksException` | `JwksException` |
| `SingPassJwtService` | `JwtService` |
| `GetSingPassTokenServiceInterface` | `TokenExchangeServiceInterface` |
| `GetSingPassJwksServiceInterface` | `JwksServiceInterface` |
| `SingPassJwtServiceInterface` | `JwtServiceInterface` |

#### Changed Behaviour

- **Login route returns JSON**: The authentication endpoint now returns `{ "redirect_url": "..." }` JSON instead of performing a server-side redirect. Clients must `fetch` the endpoint (with same-origin credentials) and navigate to the returned URL.
- **Session-backed routes required**: All routes must be behind session middleware (`web` group) for DPoP key storage, PKCE verifiers, and CSRF state verification.
- **`SingPassUser` model changes**: The `sub` claim is now a UUID (not a composite NRIC/UUID string). NRIC is available via `getNric()` only when the `user.identity` scope is requested; it returns `?string` instead of `string`.
- **MyInfo uses dedicated routes**: MyInfo flows use `/ndi/mi/initiate` and `/ndi/mi/callback` instead of sharing the SingPass login route. Separate client credentials (`SINGPASS_MYINFO_CLIENT_ID` / `SINGPASS_MYINFO_REDIRECT_URI`) are required.
- **Discovery must include PAR endpoint**: The OpenID discovery response must contain `pushed_authorization_request_endpoint`. Incomplete discovery responses throw `OpenIdDiscoveryException`.
- **Config additions required**: New configuration keys in `singpass-login.php`: `login_scopes`, `available_scopes`, `enable_default_myinfo_routes`, `dpop_signing_algorithm`, `authentication_context_type`, `authentication_context_message`, MyInfo route/controller keys. Re-publish config after upgrading.

### Migration Guide

1. **Re-publish configuration**: `php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="config" --force`
2. **Update `.env`**: Add new required variables (`SINGPASS_MYINFO_CLIENT_ID`, `SINGPASS_MYINFO_REDIRECT_URI`). See README for the full list.
3. **Update client-side code**: Replace any server-side redirects to the login endpoint with `fetch` + `window.location.assign(redirect_url)`.
4. **Update exception references**: Rename any caught exceptions per the table above.
5. **Update service references**: If you injected `SingPassLogin`, `SingPassLoginInterface`, or the facade, switch to `FapiAuthenticationService` / `FapiCallbackService` via dependency injection.
6. **Update listener**: If your listener accesses `getNric()`, add a null check — NRIC requires the `user.identity` scope and returns `?string`.
7. **Review scopes**: Configure `login_scopes` and `available_scopes` in `singpass-login.php` to match your application's needs.

---

## v2.0.1 — 2025-12-09

### Added

- MyInfo Integration Part 2: completed the MyInfo data retrieval flow with `MyInfoDataRetrievedEvent`, UserInfo endpoint support (JWE decryption + JWS verification), and updated callback handling for the MyInfo path.

### Dependencies

- Bumped `phpstan/phpstan` from 2.1.32 to 2.1.33.

---

## v2.0.0 — 2025-12-01

### Added

- **MyInfo Integration**: Added `GetUserInfoService` with JWE/JWS processing, `ScopeValidationService` for validating requested scopes, `MyInfoDataRetrievedEvent`, and UserInfo-specific exceptions (`UserInfoRequestException`, `UserInfoDecryptionException`, `UserInfoVerificationException`).
- **`TokenResponseDto`**: New DTO for token exchange responses carrying both `idToken` and optional `accessToken`.
- Scope-based routing in `GetAuthenticationEndpointController` to determine login vs MyInfo flows.
- Configurable `login_scopes` and `available_scopes` in `singpass-login.php`.
- MyInfo client credentials (`SINGPASS_MYINFO_CLIENT_ID`, `SINGPASS_MYINFO_REDIRECT_URI`).
- PHPStan configuration (`phpstan.neon`) for static analysis.

### Changed

- Updated `SingPassJwtService` with additional JWT processing methods for UserInfo tokens.
- Updated `GetSingPassTokenService` to return `TokenResponseDto` instead of raw ID token string.
- Expanded `GetAuthenticationEndpointController` to handle scope-based MyInfo/Login routing.
- Enhanced exception classes with `@param` annotations.

### Dependencies

- Bumped `orchestra/testbench` from 10.4.0 to 10.8.0.
- Bumped `web-token/jwt-framework` from 4.0.4 to 4.1.2.
- Bumped `guzzlehttp/guzzle` from 7.9.3 to 7.10.0.
- Bumped `laravel/pint` from 1.25.1 to 1.26.0.
- Bumped `phpstan/phpstan` from 2.1.17 to 2.1.32.
- Bumped various GitHub Actions to latest versions.

### Breaking Changes

- `GetSingPassTokenServiceInterface::getToken()` now returns `TokenResponseDto` instead of `string`.
- New required config keys: `login_scopes`, `available_scopes`, `myinfo_client_id`, `myinfo_redirect_uri`.

---

## v1.3.0 — 2025-06-13

### Added

- `SingPassGetEndpointException`: New exception thrown when the authentication endpoint is accessed directly or with missing parameters.

### Fixed

- Callback controller now catches and handles cases where the endpoint is accessed with missing parameters instead of throwing an unhandled error.

### Changed

- Upgraded `orchestra/testbench` from 9.13.1 to 10.4.0.
- Updated GitHub Actions CI workflows to remediate deprecation warnings.

### Dependencies

- Bumped `phpstan/phpstan` from 2.1.13 to 2.1.17.
- Bumped `laravel/pint` from 1.22.0 to 1.22.1.
- Bumped `symfony/clock` from 7.2.0 to 7.3.0.

---

## v1.2.0 — 2025-04-29

*No functional changes from v1.1.0. Tag re-release.*

---

## v1.1.0 — 2025-04-29

### Added

- **PKCE support**: Added `CodeChallengeVerifierService` implementing S256 code challenge for the authorization flow.
- **Laravel 12 compatibility**.

### Dependencies

- Bumped `laravel/pint` from 1.18.1 to 1.22.0.
- Bumped `phpstan/phpstan` from 2.1.6 to 2.1.13.
- Bumped `guzzlehttp/guzzle` from 7.9.2 to 7.9.3.
- Bumped `web-token/jwt-framework` from 4.0.3 to 4.0.4.

---

## v1.0.1 — 2025-02-25

### Changed

- Updated `SingPassLoginException` to redirect with error details instead of a generic failure response.

---

## v1.0.0 — 2025-02-24

Initial release.

### Features

- SingPass OIDC login flow with JWE decryption and JWS verification.
- `SingPassUser` model with NRIC and UUID extraction from ID token `sub` claim.
- `SingPassSuccessfulLoginEvent` fired on successful authentication.
- Configurable and publishable listener (`SingPassSuccessfulLoginListener`).
- OpenID Connect Discovery with caching.
- JWKS endpoint controller for exposing application signing keys.
- JWT client assertion generation for token exchange.
- Configurable routes, controllers, and redirect URIs via `singpass-login.php` config and environment variables.
- Full test suite with unit and feature tests.
