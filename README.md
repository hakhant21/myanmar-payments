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

### Query Payment Status

```php
use Hakhant\Payments\Application\PaymentManager;

public function status(string $transactionId, PaymentManager $payments): array
{
    $response = $payments->provider('kbzpay')->queryStatus($transactionId);

    return [
        'transaction_id' => $response->transactionId,
        'status' => $response->status->value,
        'provider' => $response->provider,
    ];
}
```

### Refund (Provider Optional Capability)

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use RuntimeException;

public function refund(string $transactionId, PaymentManager $payments): array
{
    $gateway = $payments->provider('kbzpay');

    if (! $gateway instanceof CanRefundPayment) {
        throw new RuntimeException('Selected provider does not support refunds.');
    }

    $response = $gateway->refund(new RefundRequest(
        transactionId: $transactionId,
        amount: 10000,
        reason: 'Customer requested cancellation'
    ));

    return [
        'refund_id' => $response->refundId,
        'status' => $response->status->value,
    ];
}
```

### Verify Callback Signature (Provider Optional Capability)

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Illuminate\Http\Request;
use RuntimeException;

public function webhook(Request $request, PaymentManager $payments)
{
    $gateway = $payments->provider('kbzpay');

    if (! $gateway instanceof CanVerifyCallback) {
        throw new RuntimeException('Selected provider does not support callback verification.');
    }

    // Whitelist provider callback fields instead of accepting the full request payload.
    $callbackData = $request->only([
        'merch_order_id',
        'prepay_id',
        'mm_order_id',
        'trade_status',
        'trade_type',
        'total_amount',
        'currency',
        'pay_success_time',
        'nonce_str',
    ]);

    $payload = new CallbackPayload(
        payload: $callbackData,
        signature: (string) $request->header('X-Signature', ''),
        timestamp: $request->header('X-Timestamp') !== null
            ? (int) $request->header('X-Timestamp')
            : null,
    );

    $valid = $gateway->verifyCallback($payload);

    abort_unless($valid, 401, 'Invalid signature');

    return response()->json(['ok' => true]);
}
```

### Webhook Security Notes

- Whitelist only the callback fields your provider signs. Avoid using full request payloads.
- Keep signature input format consistent with provider docs (raw body vs parsed fields).
- Reject callbacks with missing signature headers.
- Enforce replay protection using timestamp validation (for example, reject if older than 5 minutes).
- Use idempotency keys (such as `prepay_id` or provider transaction ID) to prevent duplicate processing.
- Return non-2xx for invalid signatures and do not mutate payment state.
- Log minimal callback metadata and redact sensitive values.

### MMQR Usage

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use RuntimeException;

public function createMmqr(PaymentManager $payments): array
{
    $gateway = $payments->provider('kbzpay');

    if (! $gateway instanceof CanInitiateMmqr) {
        throw new RuntimeException('Selected provider does not support MMQR.');
    }

    $response = $gateway->createMmqr(new MmqrRequest(
        merchantReference: 'MMQR-1001',
        amount: 10000,
        currency: 'MMK',
        notifyUrl: 'https://example.com/payments/mmqr/callback',
        metadata: ['invoice_no' => 'INV-1001'],
    ));

    return [
        'transaction_id' => $response->transactionId,
        'status' => $response->status->value,
        'qr_code' => $response->qrCode,
        'qr_image' => $response->qrImage,
    ];
}
```

### Facade Usage

```php
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Laravel\Facades\MyanmarPayments;

$response = MyanmarPayments::provider('kbzpay')->createPayment(
    new PaymentRequest(
        merchantReference: 'INV-2001',
        amount: 25000,
        currency: 'MMK',
        callbackUrl: 'https://example.com/payments/callback',
        redirectUrl: 'https://example.com/payments/return'
    )
);
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
