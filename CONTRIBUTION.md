# Contributing Guide

Thank you for contributing to `hakhant/myanmar-payments`.

## Development Setup

1. Clone the repository.
2. Install dependencies:

```bash
composer install
```

1. Run quality checks:

```bash
composer format
composer analyse
composer test
composer test:coverage
```

## Coding Rules

- PHP 8.2+ only
- `declare(strict_types=1);` in every PHP file
- Strict return types on all methods
- Keep namespaces under `Hakhant\\Payments`
- Follow SOLID and existing architecture (Domain/Application/Infrastructure/Laravel)
- Prefer immutable DTOs and value objects
- Keep provider-specific logic inside provider adapters

## Testing Requirements

- Use PestPHP for all new tests
- Add unit tests for behavior and edge cases
- Add feature tests for framework integration
- Do not call real third-party APIs in tests
- New changes should keep or increase coverage

## Custom Provider Integration Guide

This guide explains how to integrate a custom payment provider into `hakhant/myanmar-payments` while preserving SOLID principles and established package patterns.

### 1. Choose Capabilities

Every provider must implement `PaymentGateway`.

Optional capabilities:

- `CanRefundPayment`
- `CanVerifyCallback`
- `CanInitiateMmqr` (if provider supports MMQR-like QR payments)

### 2. Create Provider Classes

Create a new provider folder under `src/Infrastructure/Providers/<ProviderName>/`.

Recommended classes:

- `<ProviderName>Gateway`
- `<ProviderName>Client`
- `<ProviderName>Signature`
- `<ProviderName>Mapper`

Responsibilities:

- Gateway: orchestration and contract implementation
- Client: HTTP transport only
- Signature: sign/verify only
- Mapper: convert raw payloads to package DTOs

### 3. Add Configuration

Add provider config in `config/myanmar-payments.php` under `providers`.

Use environment keys for all sensitive runtime values.

Example skeleton:

```php
'providers' => [
 'newpay' => [
  'merchant_code' => env('NEWPAY_MERCH_CODE', ''),
  'app_id' => env('NEWPAY_APP_ID', ''),
  'secret' => env('NEWPAY_SECRET', ''),
  'notify_url' => env('NEWPAY_NOTIFY_URL', ''),
  'endpoints' => [
   'create' => env('NEWPAY_CREATE_URL', ''),
   'query' => env('NEWPAY_QUERY_URL', ''),
   'refund' => env('NEWPAY_REFUND_URL', ''),
  ],
  'timeout' => (int) env('NEWPAY_TIMEOUT', 30),
 ],
],
```

### 4. Register in Factory

Update `DefaultGatewayFactory` to build your provider gateway using its config.

Pattern to follow:

- Resolve provider config
- Construct provider dependencies explicitly
- Return gateway as `PaymentGateway`

### 5. Add Tests

Minimum tests required:

- Signature sign/verify behavior
- Mapper status conversion
- Client request envelope/headers
- Gateway create/query/refund behavior
- Callback verification (valid, invalid, tampered)
- Factory resolution and unsupported provider paths

Use `Http::fake()` for HTTP tests. Never call real provider APIs.

### 6. Provider Contract Expectations

Your provider should:

- Throw package exceptions (`ProviderException`, `ProviderUnavailableException`, `ValidationException`)
- Keep raw payloads in DTO `raw` for debugging
- Redact secrets in logs
- Use idempotency safeguards for callbacks where applicable

### 7. Done Criteria

- All tests pass: `composer test`
- Coverage verified: `composer test:coverage`
- Static analysis passes: `composer analyse`
- Formatter clean: `composer format`
- Full quality pipeline passes: `composer quality`

## Pull Request Checklist

- [ ] Code is formatted (`composer format`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Tests pass (`composer test`)
- [ ] Coverage checked (`composer test:coverage`)
- [ ] Docs updated when behavior/config changed

## Commit Guidelines

Use clear commit messages focused on intent, for example:

- `feat(kbz): add mmqr precreate support`
- `test(kbz): add webhook signature verification cases`
- `docs: add custom provider integration guide`

## Reporting Issues

Include:

- PHP version
- Laravel version
- Package version/branch
- Reproduction steps
- Expected behavior
- Actual behavior
- Stack trace/log snippets (redact secrets)
