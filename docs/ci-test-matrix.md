# CI test matrix

The `Run Tests` workflow (`.github/workflows/run-test.yml`) does tests of this package on each supported combination of
PHP and Laravel.

## Coverage

Two axes (PHP version, Laravel version) make **10 legs**:

|                               | PHP 8.2  | PHP 8.3 | PHP 8.4 | PHP 8.5  |
|-------------------------------|----------|---------|---------|----------|
| **Laravel 11** (testbench 9)  | ✅        | ✅       | ✅       | excluded |
| **Laravel 12** (testbench 10) | ✅        | ✅       | ✅       | ✅        |
| **Laravel 13** (testbench 11) | excluded | ✅       | ✅       | ✅        |

- Each leg resolves with one `dependency-version` value: `prefer-stable`.
- Exclusion **PHP 8.5 × Laravel 11**: Laravel 11 does not operate on PHP 8.5.
- Exclusion **PHP 8.2 × Laravel 13**: Laravel 13 and testbench 11 require PHP 8.3 or later.

## Workflow structure

- `run-test.yml` is a `workflow_call` workflow, not a standalone workflow.
- Two workflows start it:
    - `feature.yml` — pull requests and pushes to master
    - `merge_to_master.yml` — pushes to master

## Resolution strategy

- The matrix uses `prefer-stable` only. This does a test with the current releases.
- CI does **not** do tests of the constraint floors.
- A floor such as `illuminate/support: ^11.3` or `web-token/jwt-framework: ^4.0.2` is a claim without a test.
- If you change a floor, examine the effect, or do a [local test](#do-a-test-of-one-leg-locally).
- Example of the gap — the `jwt-framework` floor:
    - The declared constraint was `^4.0.1`.
    - Version 4.0.1 refers to `RangeException` without a namespace in `Jose\Component\Core\Util`.
    - Thus PHP causes a fatal `Error`, not an exception.
    - The `catch (Exception)` in `generateClientAssertion()` did not catch it. `GenerateClientAssertionTest` failed.
    - Each `prefer-stable` leg stayed green. Only a lowest-resolution run found the defect.
    - The floor is now `^4.0.2`, the first release that has the import.

## How dependency is resolved for each leg

```yaml
composer require --dev --no-update --no-interaction "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}"
composer update --${{ matrix.dependency-version }} --prefer-dist --no-interaction --no-progress
```

- The workflow does not use the committed `composer.lock`.
- The first command writes a new `composer.json` in the runner.
- Thus each leg replaces the `orchestra/testbench` constraint, and CI does not do a test of it.
- The `require` constraints and the other `require-dev` entries stay applicable.
- PHPUnit gets `--no-coverage` because `phpunit.xml` declares a clover report and the matrix has `coverage: none`.
- Without the flag, PHPUnit stops with a non-zero code.
- `ci.yml` keeps the ownership of coverage (Xdebug).

## Relation to `ci.yml`

- `ci.yml`: coverage gate, automatic Pint commit, Sonar scan. One tree — PHP 8.3 with the committed `composer.lock`.
- The matrix: new resolutions across the PHP and Laravel versions.
- The two results are independent: a green `ci` does not show a green matrix, and the opposite is also true.
- The two stay separate to prevent two problems:
    1. Matrix jobs cannot supply a reliable workflow output; `ci.yml` sends `coverage` to `badge.yml`.
    2. More than one leg on `git-auto-commit-action` causes a race condition.
- Future considerations to refactor the two.

## Security advisory policy

- This repository has no advisory suppression (`policy.advisories.block`, `ignore-id`, `audit.ignore`).
- Thus the default Composer policy is applicable.
- From Composer 2.10, the **resolver** applies this policy, not an audit after the installation:
    - The resolver removes each version that has a known advisory from the candidate pool.
- Thus a leg can degrade or fail at resolution — this is different from a test failure:
    - If a clean tagged version stays in the range, the resolver installs it. This is the usual result.
    - If not, `minimum-stability: dev` lets the resolver use a matching dev branch (refer to the Laravel 11 section).
    - If no matching branch exists, the resolution fails. Consumer projects have no dev fallback and fail at once.
- Correction: increase the floor in `composer.json`. Do not suppress the advisory.

## Known limitation: the tests do not include a released Laravel 11 version

- The security support for Laravel 11 stopped in March 2026.
- Seven security advisories remove each tagged 11.x release. Three will not get a corrected version.
- Composer will not install one of these releases.
- Thus the Laravel 11 legs resolve the untagged `11.x-dev` branch tip. An application cannot install this code.
- The CI logs show this: `Installing laravel/framework (11.x-dev c0f062f)`.
- **A green Laravel 11 leg is not verification of a Laravel 11 release that an application can install.**
- The Laravel 12 legs give the important coverage.
- This is the only reason for the `composer.json` pair `minimum-stability: dev` and `prefer-stable: true`:
    - `minimum-stability: dev` permits the fallback to `11.x-dev`. Without it, the Laravel 11 resolution fails.
    - `prefer-stable: true` keeps the other dependencies on tagged releases. The dev stability does not spread.
- An application that must use Laravel 11 must permit those advisories in its own Composer configuration.

## Doing a test of one leg locally

Do this if you wish to test locally for a specific laravel version.
Use a temporary clone. This procedure writes new `composer.json` and `composer.lock` files.

```bash
composer require --dev --no-update "laravel/framework:12.*" "orchestra/testbench:10.*"
composer update --prefer-stable --prefer-dist
vendor/bin/phpunit --no-coverage
```

- Change the two version constraints to the values of the applicable leg.
- Use the correct pair: Laravel 11 → testbench 9, Laravel 12 → testbench 10, Laravel 13 → testbench 11.
- To do a test of a constraint floor, use `--prefer-lowest`. No CI leg does this test.
- Note: `prefer-lowest` resolves the lowest set of compatible versions, not the literal floor of each constraint.
- Thus the installed versions are different for each leg.
