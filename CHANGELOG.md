# Changelog

## v4.1.0

- Add Laravel 13 support: `illuminate/*` constraints now accept `^13.0`
- Add Laravel 13 CI legs (testbench 11) on PHP 8.3, 8.4 and 8.5
- Dev deps: allow testbench `^11.0`, raise phpunit floor to `^11.5.50` (testbench 11 requirement)
- No code changes needed; the v4.0.0 cache work already prepared for Laravel 13

## v4.0.0

Maintenance release with three changes:

- Laravel 10 support is removed.
- A CI test matrix is added. It does tests of each supported PHP and Laravel combination.
- The OpenID configuration is now cached as a plain array. This prepares the package for Laravel 13.

Notes:

- Laravel 13 support is not part of this release. It comes in a later release.
- The version increase is major because Laravel 10 support is removed.
- Projects on v3.x resolve as before. Only an explicit upgrade to v4 gets the new constraints.

### OpenID configuration cached as a plain array

- The cache now holds the OpenID discovery configuration as a plain array, not a serialized DTO.
- Laravel 13 [`cache.serializable_classes`][l13] restricts the classes that the cache can deserialize.
- With a plain array, applications do not add this package's DTO to their allowlist.
- Reads use the new `OpenIdConfigurationDto::fromCache()` method:
    - It validates the array with the same rules as the discovery response.
    - It returns `null` for data that is not usable.
- **The discovery writer is self-healing:**
    - `cacheOpenIdDiscovery()` discards a cache entry that it cannot read back. It then does discovery again.
    - It does not keep a bad entry until the TTL expires.
    - Examples of bad entries: a serialized DTO from a previous version, an entry that an allowlist rejects.

[l13]: https://laravel.com/docs/13.x/upgrade#cache-serializable_classes-configuration

### Upgrade notes

- No action is necessary for the cache transition.
- The next authentication flow that does discovery discards and refreshes the old entries automatically.
- If you read the OpenID cache key directly: the payload is now an array, not a DTO instance.
- The array keys are the same as the keys of the discovery response:
    - `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, `jwks_uri`
    - `pushed_authorization_request_endpoint`
- To get the object, use `OpenIdConfigurationDto::fromCache()`.

### Laravel 10 support removed

- The Illuminate constraints changed from `^10.0||^11.0||^12.0` to `^11.3||^12.0`.
- The narrower bound describes the versions that operate correctly.
- **Laravel 10** was not installable:
    - `web-token/jwt-framework ^4.0` needs Symfony 7, but Laravel 10 pins Symfony 6. No solution exists.
    - Before, applications on Laravel 10 got an unclear transitive Symfony error.
    - Now, Composer refuses with a message that gives the real requirement.
- **Laravel 11.0 – 11.2** do not have `Http::createPendingRequest()`:
    - Laravel 11.3 added this method. The package uses it for JWKS retrieval and OpenID discovery.
    - Before, these versions installed, then failed at runtime on the first JWKS fetch.
    - Now, Composer rejects them at installation time.

### CI matrix testing

- A new `Run Tests` workflow does tests of PHP 8.2 – 8.5 with Laravel 11 and 12, resolved with `--prefer-stable`.
- It is a reusable workflow. `feature.yml` and `merge_to_master.yml` start it.
- It runs in parallel with `ci`. Thus it does not increase the critical path.
- The coverage badge waits for `ci` and the matrix. Thus the badge shows a commit that passed each leg.
- Refer to [`docs/ci-test-matrix.md`](docs/ci-test-matrix.md) for the matrix, the exclusions, and the advisory policy.

### composer.json tidy-up

- **The Illuminate packages are now declared explicitly:**
    - Before, only `illuminate/contracts` was declared. It covered 2 of the 67 Illuminate imports.
    - The other imports resolved implicitly, because `laravel/framework` replaces the Illuminate packages.

| Package | Covers |
|---|---|
| `illuminate/database` | Eloquent `Model`, `Builder`, `Factories\HasFactory` |
| `illuminate/http` | `Request`, `JsonResponse`, `RedirectResponse`, `Client\ConnectionException` |
| `illuminate/routing` | `Controller` |
| `illuminate/support` | `ServiceProvider`, `Str`, and the `Http`/`Cache`/`Log`/`Event`/`Auth` facades |

- There is no change in behavior — `laravel/framework` satisfies all of them.
- `Illuminate\Foundation\Auth\User` (used by `Models\User`) stays implicit.
- It ships only in `laravel/framework`. No standalone package contains it.
- **`minimum-stability` is set to `dev`, with `prefer-stable: true`:**
    - This agrees with the convention in the Laravel package ecosystem.
    - The resolver always prefers stable releases. Dev branches apply only when no stable candidate stays.
    - This also lets the Laravel 11 matrix legs resolve (refer to `docs/ci-test-matrix.md`).

## v3.0.0

### Config Split & Validation

The monolithic `singpass-login.php` has been split into focused config files with properly namespaced environment variables:

| Config file | Purpose | Env prefix |
|---|---|---|
| `config/ndi.php` | Shared NDI infrastructure (JWKS, signing, DPoP, logging, JWKS endpoint) | `NDI_` |
| `config/singpass-login.php` | SingPass Login (credentials, routes, listener, login scopes) | `SINGPASS_` |
| `config/myinfo.php` | MyInfo (credentials, routes, available scopes, login scopes) | `MYINFO_` |
| `config/corppass-login.php` | CorpPass (unchanged) | `CORPPASS_` |

**Renamed environment variables:**

| Old | New |
|---|---|
| `SINGPASS_SIGNING_KID` | `NDI_SIGNING_KID` |
| `SINGPASS_JWKS` | `NDI_JWKS` |
| `SINGPASS_PRIVATE_JWKS` | `NDI_PRIVATE_JWKS` |
| `SINGPASS_DPOP_SIGNING_ALGORITHM` | `NDI_DPOP_SIGNING_ALGORITHM` |
| `SINGPASS_LOGS_ENABLED` | `NDI_LOGS_ENABLED` |
| `SINGPASS_JWKS_URL` | `NDI_JWKS_URL` |
| `SINGPASS_MYINFO_CLIENT_ID` | `MYINFO_CLIENT_ID` |
| `SINGPASS_MYINFO_REDIRECT_URI` | `MYINFO_REDIRECT_URI` |
| `SINGPASS_USE_DEFAULT_MYINFO_ROUTES` | `MYINFO_USE_DEFAULT_ROUTES` |
| `SINGPASS_MYINFO_AUTHENTICATION_URL` | `MYINFO_AUTHENTICATION_URL` |
| `SINGPASS_MYINFO_CALLBACK_URL` | `MYINFO_CALLBACK_URL` |

**Config validation:** `ProviderConfig` factory methods now validate required config values (`discovery_endpoint`, `client_id`, `redirect_uri`, `domain`) before construction and throw `MissingConfigException` with a clear message identifying the missing key, instead of allowing PHP `TypeError` on `null` values.

### FAPI 2.0 Compliance

The package now implements the full **FAPI 2.0** security profile as required by SingPass, MyInfo, and CorpPass:

- **Pushed Authorization Requests (PAR)**: Authorization parameters are sent via a server-to-server POST, returning a `request_uri` for the browser redirect.
- **Demonstration of Proof of Possession (DPoP)**: Ephemeral key pairs are generated per session. DPoP proof JWTs are attached to PAR, token exchange, and UserInfo requests.
- **PKCE (S256)**: Code challenge/verifier pairs secure the authorization code exchange.
- **Private-key JWT client assertions**: Client authentication uses signed JWTs with `jti` claims instead of client secrets.
- **JWE-encrypted ID tokens**: ID tokens are decrypted (JWE) and verified (JWS) before payload extraction.
- **OpenID Connect Discovery validation**: Discovery responses are validated and cached via a typed `OpenIdConfigurationDto` that enforces the presence of required endpoints.
- **Diagnostic logging**: All service calls (OpenID Discovery, PAR, token exchange, JWKS, UserInfo, JWE/JWS verification, ID token claim checks) are now logged with `[SingPass]` prefix. Logging is gated behind `NDI_LOGS_ENABLED=true` (defaults to `false`). Sensitive values (`client_assertion`, `code_verifier`, `id_token`, `access_token`) are automatically redacted. Session IDs are logged on both login initiation and callback to help diagnose session-loss issues.

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
- **Separate client credentials**: `MYINFO_CLIENT_ID` and `MYINFO_REDIRECT_URI` (in `config/myinfo.php`) with an independent OpenID discovery cache.
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
| `MYINFO_USE_DEFAULT_ROUTES` | `true` | MyInfo routes |
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
| `MissingConfigException` | Required config value is `null` — thrown by `ProviderConfig` factories with a message identifying the missing key |
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
- **`SingPassUser` model changes**: The `sub` claim is now a UUID (not a composite NRIC/UUID string). NRIC/FIN is available on the readonly **`nric`** property (from `sub_attributes.identity_number`) when the `user.identity` scope is requested (`?string`). The accessor **`getNric()`** is deprecated in favour of **`$nric`**.
- **MyInfo uses dedicated routes and config**: MyInfo flows use `/ndi/mi/initiate` and `/ndi/mi/callback` instead of sharing the SingPass login route. MyInfo has its own config file (`config/myinfo.php`) with separate client credentials (`MYINFO_CLIENT_ID` / `MYINFO_REDIRECT_URI`).
- **Discovery must include PAR endpoint**: The OpenID discovery response must contain `pushed_authorization_request_endpoint`. Incomplete discovery responses throw `OpenIdDiscoveryException`.
- **Config split**: Configuration has been split from a single `singpass-login.php` into four files (`ndi.php`, `singpass-login.php`, `myinfo.php`, `corppass-login.php`). Several environment variables have been renamed — see the "Config Split & Validation" section above. Re-publish config after upgrading.

### Migration Guide

1. **Re-publish configuration**: `php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="config" --force`
2. **Rename environment variables**: Rename env vars per the table in "Config Split & Validation" above (e.g. `SINGPASS_SIGNING_KID` → `NDI_SIGNING_KID`, `SINGPASS_MYINFO_CLIENT_ID` → `MYINFO_CLIENT_ID`). See README for the full list.
3. **Update client-side code**: Replace any server-side redirects to the login endpoint with `fetch` + `window.location.assign(redirect_url)`.
4. **Update exception references**: Rename any caught exceptions per the table above.
5. **Update service references**: If you injected `SingPassLogin`, `SingPassLoginInterface`, or the facade, switch to `FapiAuthenticationService` / `FapiCallbackService` via dependency injection.
6. **Update listener**: Use **`$event->getSingPassUser()->nric`** for NRIC/FIN (add a null check — requires the `user.identity` scope). Avoid **`getNric()`**; it is deprecated.
7. **Review scopes**: Configure `login_scopes` in `singpass-login.php` and `available_scopes` in `myinfo.php` to match your application's needs.

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
