# Myanmar Payments

Laravel package for Myanmar payments, focused on KBZPay, MMQR, 2C2P, and WaveMoney integrations, with secure callback verification, strict typing, and an extensible provider architecture.

[![Tests](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml)
[![PHPStan Analyse](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/analyse.yml)
[![Packagist Downloads](https://img.shields.io/packagist/dt/hakhant/myanmar-payments)](https://packagist.org/packages/hakhant/myanmar-payments)
[![License](https://img.shields.io/github/license/hakhant21/myanmar-payments.svg)](LICENSE.md)

## Features

- Strict typing with `declare(strict_types=1);`
- PSR-4 autoloading
- Strategy + Factory provider architecture
- Provider adapter for 2C2P redirect checkout and refund maintenance
- Provider adapter for WaveMoney payment creation and callback verification
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

TWOC2P_MERCHANT_ID=
TWOC2P_SECRET_KEY=
TWOC2P_MERCHANT_PRIVATE_KEY=
TWOC2P_PUBLIC_KEY=
TWOC2P_KEY_ID=
TWOC2P_LOCALE=en
TWOC2P_PAYMENT_DESCRIPTION=Payment
TWOC2P_MAINTENANCE_VERSION=4.3
TWOC2P_REFUND_NOTIFY_URL=
TWOC2P_REFUND_IDEMPOTENCY_ID=
TWOC2P_PAYMENT_TOKEN_URL=https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken
TWOC2P_TRANSACTION_STATUS_URL=https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus
TWOC2P_REFUND_URL=https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action

WAVEMONEY_MERCHANT_ID=
WAVEMONEY_SECRET_KEY=
WAVEMONEY_MERCHANT_NAME=
WAVEMONEY_PAYMENT_DESCRIPTION=Payment
WAVEMONEY_TTL_SECONDS=600
WAVEMONEY_PAYMENT_URL=https://testpayments.wavemoney.io:8107/payment
WAVEMONEY_AUTHENTICATE_URL=https://testpayments.wavemoney.io/authenticate

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
    $response = $payments->provider('2c2p')->createPayment(
        new PaymentRequest(
            merchantReference: 'INV-1001',
            amount: 10000,
            currency: 'MMK',
            callbackUrl: 'https://example.com/payments/callback',
            redirectUrl: 'https://example.com/payments/return',
            metadata: ['description' => 'Order INV-1001']
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
    $response = $payments->provider('2c2p')->queryStatus($transactionId);

    return [
        'transaction_id' => $response->transactionId,
        'status' => $response->status->value,
        'provider' => $response->provider,
    ];
}
```

For 2C2P, `transactionId` is the returned payment token because the transaction-status endpoint queries by payment token.

### Refund (Provider Optional Capability)

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use RuntimeException;

public function refund(string $transactionId, PaymentManager $payments): array
{
    $gateway = $payments->provider('2c2p');

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
    $gateway = $payments->provider('2c2p');

    if (! $gateway instanceof CanVerifyCallback) {
        throw new RuntimeException('Selected provider does not support callback verification.');
    }

    $payload = new CallbackPayload(
        payload: ['payload' => (string) $request->input('payload', '')],
        signature: '',
    );

    $valid = $gateway->verifyCallback($payload);

    abort_unless($valid, 401, 'Invalid signature');

    return response()->json(['ok' => true]);
}
```

### 2C2P Notes

- The implemented 2C2P provider supports hosted redirect checkout plus refund maintenance requests.
- Use a sufficiently long HS256 secret key. `firebase/php-jwt` rejects short keys.
- `createPayment()` requests a payment token and returns the hosted `webPaymentUrl`.
- `queryStatus()` uses the transaction-status endpoint and expects the payment token returned by `createPayment()`.
- Callback verification decodes and verifies the signed JWT payload returned by 2C2P.
- `refund()` uses the payment-maintenance endpoint with XML wrapped in JWE/JWS using your merchant private key and the 2C2P public key.
- Refund support requires PEM-formatted `TWOC2P_MERCHANT_PRIVATE_KEY` and `TWOC2P_PUBLIC_KEY` values from the 2C2P key-exchange setup.
- `TWOC2P_KEY_ID` is optional and can be set when your 2C2P account expects a `kid` header in the signed JWS.
- Asynchronous refund completion callbacks and refund-status inquiry are not wrapped yet. The current package support covers refund initiation and mapping the immediate maintenance response.

### WaveMoney Notes

- `createPayment()` posts the documented form payload to WaveMoney `/payment` and returns a redirect `paymentUrl` using `/authenticate?transaction_id=...`.
- Request hashing follows the WaveMoney formula: `time_to_live_in_seconds + merchant_id + order_id + amount + backend_result_url + merchant_reference_id` using HMAC SHA256.
- Callback verification follows the WaveMoney callback formula and treats null values as the literal string `null`, as required by docs.
- `queryStatus()` is intentionally unsupported for WaveMoney in this package because the provided docs define callback-driven status updates but no status inquiry endpoint.
- Treat callback status `PAYMENT_CONFIRMED` as success and verify hash before updating payment state.

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
