# Myanmar Payments

Laravel package for Myanmar payments, focused on KBZPay and MMQR, with secure webhook signature verification, strict typing, and an extensible provider architecture.

[![Tests](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml)
[![PHPStan Analyse](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml)
[![Packagist Downloads](https://img.shields.io/packagist/dt/hakhant/myanmar-payments)](https://packagist.org/packages/hakhant/myanmar-payments)
[![License](https://img.shields.io/github/license/hakhant21/myanmar-payments.svg)](LICENSE.md)

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

For custom provider implementation details, see [CONTRIBUTION.md](CONTRIBUTION.md).
