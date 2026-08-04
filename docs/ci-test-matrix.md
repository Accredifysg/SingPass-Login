# CI test matrix

This package is tested across every supported PHP and Laravel combination by the
`Run Tests` workflow (`.github/workflows/run-test.yml`). This document explains what
the matrix covers, why each exclusion exists, and the known limitation around
Laravel 11.

## Coverage

Three axes — PHP version, Laravel version, and dependency resolution strategy —
producing **20 legs**:

|  | PHP 8.2 | PHP 8.3 | PHP 8.4 | PHP 8.5 |
|---|---|---|---|---|
| **Laravel 11** (testbench 9) | ✅ | ✅ | ✅ | excluded |
| **Laravel 12** (testbench 10) | ✅ | ✅ | ✅ | ✅ |
| **Laravel 13** (testbench 11) | excluded | ✅ | ✅ | ✅ |

Each of those 10 combinations runs twice, once per `dependency-version`:
`prefer-lowest` and `prefer-stable`.

Both exclusions are hard constraints rather than preferences:

- **PHP 8.5 × Laravel 11** — Laravel 11 predates PHP 8.5 and was never supported on it.
- **PHP 8.2 × Laravel 13** — `orchestra/testbench` 11 requires `php ^8.3`.

## Why two resolution strategies

`prefer-stable` answers "does the package work against current releases?"
`prefer-lowest` answers "does the package work against the floor of its own declared
constraints?" — the tree a consumer gets when their other dependencies hold everything
back. Without the lowest axis, a constraint floor is an untested claim.

This is not hypothetical. `web-token/jwt-framework` was declared as `^4.0.1`, and 4.0.1
references `RangeException` unqualified inside `Jose\Component\Core\Util`, so PHP resolves
it to the non-existent `Jose\Component\Core\Util\RangeException` and raises a fatal `Error`
instead of an exception. That `Error` is not an `Exception`, so it slipped past the
`catch (Exception)` in `JwtService::generateClientAssertion()` and broke
`GenerateClientAssertionTest`. Six `prefer-lowest` legs caught it; every `prefer-stable`
leg was green. The floor is now `^4.0.2`, the first upstream release carrying the import.

Note that `prefer-lowest` resolves the lowest *mutually compatible* set, not the literal
floor of every constraint — so the version actually installed varies per leg. On the
Laravel 12 legs `jwt-framework` resolves to 4.0.7; on Laravel 13 it resolves to 4.1.7,
because Laravel 13's Symfony requirements pull it forward.

## How each leg resolves dependencies

```yaml
composer require --dev --no-update --no-interaction \
  "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}"
composer update --${{ matrix.dependency-version }} --prefer-dist --no-interaction --no-progress
```

The committed `composer.lock` is deliberately bypassed. Installing the lock would test one
pinned tree 20 times; resolving fresh exercises the constraints that are actually published
to consumers.

`--no-coverage` is passed to PHPUnit because `phpunit.xml` declares a clover report and the
matrix runs with `coverage: none`. Without the flag there is no driver to satisfy the report
and PHPUnit exits non-zero on that runner warning. Coverage stays owned by `ci.yml`, which
runs with Xdebug.

## Relationship to `ci.yml`

`ci.yml` is untouched by the matrix and still owns the coverage gate, the Pint auto-commit
and the Sonar scan. Keeping the two separate avoids two concrete problems:

1. Matrix jobs cannot produce a reliable workflow output, and `ci.yml` exports `coverage`
   to `badge.yml`.
2. Multiple legs would race on `git-auto-commit-action`.

## Security advisory policy

No advisory suppression is configured in this repository — there is no
`policy.advisories.block`, no `ignore-id`, and no `audit.ignore`. Composer's default
policy therefore applies.

Since Composer 2.10 that policy is enforced **in the resolver**, not as a post-install
audit: versions affected by a known advisory are removed from the candidate pool entirely,
and resolution fails if nothing clean remains in range. The practical consequence for this
matrix is that a `prefer-lowest` leg can fail to *resolve* — distinct from failing tests —
when a dependency's floor range is fully covered by advisories. Fix that by raising the
floor in `composer.json`, not by suppressing the advisory.

## Known limitation: Laravel 11 is not covered against a released version

Laravel 11 left security support in Mar 2026, and every tagged 11.x release is excluded by
seven security advisories — three of which will never have a fixed version. Under the
default policy above, Composer will not install any of them.

The Laravel 11 legs therefore resolve the untagged `11.x-dev` branch tip (verified in CI
logs: `Installing laravel/framework (11.x-dev c0f062f)`), which is unreleased code no
application can install. **A green Laravel 11 leg is not verification of an installable
Laravel 11 release.** The Laravel 12 and 13 legs resolve real tagged releases and are the
meaningful coverage.

Applications wanting to install this package on Laravel 11 will need to allow those
advisories in their own Composer configuration.

## Reproducing a leg locally

Work on a scratch clone — this overwrites `composer.json` and `composer.lock`:

```bash
composer require --dev --no-update "laravel/framework:13.*" "orchestra/testbench:11.*"
composer update --prefer-lowest --prefer-dist
vendor/bin/phpunit --no-coverage
```

Swap `--prefer-lowest` for `--prefer-stable`, and the two version constraints, to match
whichever leg you are chasing. The `testbench` version must be paired correctly:
Laravel 11 → testbench 9, Laravel 12 → testbench 10, Laravel 13 → testbench 11.
