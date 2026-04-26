# Contributing Guide

Thank you for contributing to `hakhant/myanmar-payments`.

This package is a Laravel payment abstraction for Myanmar providers. The current project flow centers on provider adapters, typed DTOs, capability-based contracts, Pest tests, PHPStan analysis, Pint formatting, and a tagged release process driven by the `VERSION` file.

## Project Flow

1. Install dependencies with Composer.
2. Make changes inside the existing package layers instead of mixing provider, domain, and framework concerns.
3. Add or update Pest tests alongside the change.
4. Run local quality checks before opening a pull request.
5. Open a pull request against `main`.
6. CI validates tests and static analysis on PHP 8.2.
7. Releases are created from `main` by tagging the version in `VERSION` through `composer release`.

## Development Setup

### Requirements

- PHP 8.2+
- Composer

### Install

```bash
composer install
```

### Useful Commands

```bash
composer test
composer test:coverage
composer analyse
composer format
composer refactor
composer quality
```

Notes:

- `composer test` runs the Pest test suite.
- `composer analyse` runs PHPStan.
- `composer format` runs Laravel Pint.
- `composer refactor` runs Rector.
- `composer quality` runs the full local sequence: tests, coverage, formatting, analysis, and refactor.

## Architecture Expectations

Keep changes aligned with the existing package boundaries:

- `src/Domain`: DTOs, enums, value objects, exceptions
- `src/Contracts`: capability-oriented contracts such as `CanRefundPayment` and `CanVerifyCallback`
- `src/Application`: `PaymentManager` and focused use cases like `CreatePayment`, `RefundPayment`, and `CreateMmqr`
- `src/Infrastructure`: provider gateways, clients, mappers, signature/hash helpers, and the gateway factory
- `src/Providers` and `src/Facades`: Laravel integration
- `tests/Unit` and `tests/Feature`: Pest coverage for package behavior and framework integration

Prefer the smallest change that fits this structure.

## Coding Rules

- Target PHP 8.2+ only.
- Use `declare(strict_types=1);` in every PHP file.
- Keep namespaces under `Hakhant\Payments`.
- Preserve strict parameter, property, and return types.
- Keep provider-specific API details inside provider infrastructure classes.
- Keep package-facing behavior expressed through DTOs, enums, and package exceptions.
- Follow the existing capability model instead of adding broad provider-specific branches in `PaymentManager`.
- Prefer extending the current classes and naming patterns over introducing new abstractions unless reuse is clear.

## Testing Expectations

Use Pest for all tests.

Add or update tests close to the behavior you changed:

- Unit tests for DTO mapping, signatures, hashes, request building, and provider gateway behavior
- Feature tests for Laravel container bindings, facade usage, MMQR flow, and webhook verification flow
- Factory and manager tests when provider resolution or capability handling changes

Testing rules:

- Do not call real third-party APIs in tests.
- Use Laravel HTTP fakes for HTTP-bound provider tests.
- Keep provider fixture/config setup in the existing test support patterns when possible.
- Run at least `composer test` and `composer analyse` before submitting changes.

## Current Provider Model

The package currently ships with these built-in providers:

- `kbzpay`
- `aya`
- `wavemoney`
- `2c2p`

All providers are resolved through `Hakhant\Payments\Infrastructure\Factories\GatewayFactory` and consumed through `PaymentManager`, the facade, or the application use cases.

`PaymentGateway` currently requires:

- `createPayment(PaymentRequest $request): PaymentResponse`
- `queryStatus(string $transactionId): PaymentResponse`

Optional capabilities are added through separate contracts:

- `CanRefundPayment`
- `CanVerifyCallback`
- `CanInitiateMmqr`

If you add a new provider capability, wire it through the package in the same style used by the existing manager capability checks such as `supportsMmqr()` and `supportsRefunds()`.

## Adding Or Updating A Provider

### 1. Add or update infrastructure classes

Place provider code under `src/Infrastructure/Gateways/<ProviderName>/`.

Follow the current package pattern where applicable:

- `<ProviderName>Gateway`
- `<ProviderName>Client`
- `<ProviderName>Mapper`
- `<ProviderName>Signature` or `<ProviderName>Hash`

Responsibilities should stay narrow:

- Gateway: package contract behavior and provider orchestration
- Client: HTTP transport details
- Mapper: raw payload to package DTO mapping
- Signature/hash helper: signing and verification only

### 2. Add provider configuration

Register provider configuration in `config/myanmar-payments.php` under `providers`.

Use environment-driven values for credentials, endpoints, timeouts, and provider defaults.

Keep naming aligned with the current config style:

- top-level provider key under `providers`
- nested `endpoints`
- integer `timeout`
- provider-specific credentials in snake_case

### 3. Register the provider in the factory

Update `src/Infrastructure/Factories/GatewayFactory.php` so the provider can be resolved by string key or `Provider` enum.

Follow the existing factory flow:

- normalize provider key
- load provider config
- construct dependencies explicitly
- return a `PaymentGateway`
- throw `ProviderException` for unsupported or invalid configuration

### 4. Expose behavior through the application layer only when needed

If the provider supports existing package capabilities such as refunds, callbacks, or MMQR, implement the relevant contracts and ensure `PaymentManager` can use them through the current capability checks.

Do not add provider-specific methods to the manager unless the package is intentionally expanding its public API.

### 5. Add tests

Minimum expected coverage for provider work usually includes:

- client request construction
- mapper behavior
- gateway success and failure paths
- signature or hash generation and verification
- factory resolution
- manager capability handling
- feature coverage when framework integration changes

## CI And Pull Requests

GitHub Actions currently run:

- `.github/workflows/tests.yml`: `composer test`
- `.github/workflows/analysis.yml`: `composer analyse`

Before opening a pull request:

- Run `composer test`.
- Run `composer analyse`.
- Run `composer format` when code style changed.
- Update `README.md`, `CONTRIBUTION.md`, or config docs when public behavior or configuration changes.

## Release Flow

The repository uses a version-file-driven release flow.

Current release inputs and automation:

- The `VERSION` file stores the current release version.
- `composer release` runs `bash release.sh`.
- `release.sh` syncs with `origin/main`, uses the current `VERSION`, and requires the `main` branch.
- The script syncs with `origin/main`, runs `composer test` and `composer analyse`, creates an empty release commit, and tags `v<version>`.
- Pushing the tag triggers `.github/workflows/release.yml`.
- The GitHub release workflow creates a GitHub Release and keeps `VERSION` aligned with the released tag on `main`.

If you are preparing release-related changes, verify that `VERSION`, release notes context, and any user-facing docs are consistent before running `composer release`.

## Pull Request Checklist

- [ ] Code follows the existing package layers and naming patterns
- [ ] Tests were added or updated for behavioral changes
- [ ] `composer test` passes
- [ ] `composer analyse` passes
- [ ] `composer format` was run when needed
- [ ] Documentation/config examples were updated when public behavior changed

## Commit Guidelines

Use clear, intent-focused commit messages.

Examples:

- `feat(kbzpay): add mmqr request mapping`
- `fix(wavemoney): reject invalid callback hashes`
- `test(2c2p): cover refund maintenance failures`
- `docs: align contribution guide with project workflow`

## Reporting Issues

Include:

- PHP version
- Laravel version
- Package version or branch
- Selected provider
- Reproduction steps
- Expected behavior
- Actual behavior
- Relevant logs or stack traces with secrets redacted
