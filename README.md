# hakhant/myanmar-payments

Laravel package for Myanmar payments, focused on KBZPay and MMQR, with secure webhook signature verification, strict typing, and an extensible provider architecture.

[![Tests](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml)
[![PHPStan Analyse](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml)

## Features

- Strict typing with `declare(strict_types=1);`
- PSR-4 autoloading
- Strategy + Factory provider architecture
- Provider adapter for KBZPay (including MMQR)
- KBZ callback/request signature verification with canonical SHA256 signing
- Laravel Service Provider + Facade integration
- Tooling: Pint, Rector, PHPStan, Pest

## Requirements

- PHP 8.2+
- Laravel 10/11/12

## Installation

```bash
composer require hakhant/myanmar-payments
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=myanmar-payments-config
```

## Environment

```dotenv
MM_PAYMENT_PROVIDER=kbzpay

KBZPAY_MERCH_CODE=
KBZPAY_MERCHANT_ID=
KBZPAY_APP_ID=
KBZPAY_SECRET=
KBZPAY_PUBLIC_KEY=
KBZPAY_NOTIFY_URL=https://merchant.example.com/payments/kbzpay/callback
KBZPAY_TRADE_TYPE=APP

# KBZ endpoints (prod defaults)
KBZPAY_PRECREATE_URL=https://api.kbzpay.com/payment/gateway/precreate
KBZPAY_QUERYORDER_URL=https://api.kbzpay.com/payment/gateway/queryorder
KBZPAY_REFUND_URL=https://api.kbzpay.com:8008/payment/gateway/refund
KBZPAY_MMQR_URL=https://api.kbzpay.com/payment/gateway/mmqrprecreate

# UAT examples from KBZ docs:
# KBZPAY_PRECREATE_URL=http://api-uat.kbzpay.com/payment/gateway/uat/precreate
# KBZPAY_QUERYORDER_URL=http://api-uat.kbzpay.com/payment/gateway/uat/queryorder
# KBZPAY_REFUND_URL=https://api-uat.kbzpay.com:18008/payment/gateway/uat/refund
# KBZPAY_MMQR_URL=http://api-uat.kbzpay.com/payment/gateway/uat/mmqrprecreate
```

## Usage

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\PaymentRequest;

public function checkout(PaymentManager $payments)
{
    $response = $payments->provider('kbzpay')->createPayment(
        new PaymentRequest(
            merchantReference: 'INV-1001',
            amount: 10000,
            currency: 'MMK',
            callbackUrl: 'https://example.com/payments/callback',
            redirectUrl: 'https://example.com/payments/return'
        )
    );

    return redirect()->away((string) $response->paymentUrl);
}
```

## Quality Commands

```bash
composer quality
composer format
composer analyse
composer test
composer refactor
```

## Documentation

### Custom Provider Integration Guide

This guide explains how to integrate a custom payment provider into `hakhant/myanmar-payments` while preserving SOLID principles and established package patterns.

#### 1. Choose Capabilities

Every provider must implement `PaymentGateway`.

Optional capabilities:

- `CanRefundPayment`
- `CanVerifyCallback`
- `CanInitiateMmqr` (if provider supports MMQR-like QR payments)

#### 2. Create Provider Classes

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

#### 3. Add Configuration

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

#### 4. Register in Factory

Update `DefaultGatewayFactory` to build your provider gateway using its config.

Pattern to follow:

- Resolve provider config
- Construct provider dependencies explicitly
- Return gateway as `PaymentGateway`

#### 5. Add Tests

Minimum tests required:

- Signature sign/verify behavior
- Mapper status conversion
- Client request envelope/headers
- Gateway create/query/refund behavior
- Callback verification (valid, invalid, tampered)
- Factory resolution and unsupported provider paths

Use `Http::fake()` for HTTP tests. Never call real provider APIs.

#### 6. Provider Contract Expectations

Your provider should:

- Throw package exceptions (`ProviderException`, `ProviderUnavailableException`, `ValidationException`)
- Keep raw payloads in DTO `raw` for debugging
- Redact secrets in logs
- Use idempotency safeguards for callbacks where applicable

#### 7. Done Criteria

- All tests pass: `composer test`
- Coverage verified: `composer test:coverage`
- Static analysis passes: `composer analyse`
- Formatter clean: `composer format`
- Full quality pipeline passes: `composer quality`
