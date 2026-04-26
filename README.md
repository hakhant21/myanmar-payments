# Myanmar Payments for Laravel

Laravel Myanmar payment gateway package for KBZPay, WaveMoney (Wave Pay), 2C2P, and AYA Pay with MMQR support. It provides a single gateway-selection flow, typed request and response DTOs, refunds, callbacks, and Laravel container integration.

[![Tests](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/hakhant21/myanmar-payments/actions/workflows/tests.yml)
[![Analysis](https://img.shields.io/github/actions/workflow/status/hakhant21/myanmar-payments/analysis.yml?branch=main&label=Analysis)](https://github.com/hakhant21/myanmar-payments/actions/workflows/analysis.yml)
[![Version](https://img.shields.io/packagist/v/hakhant/myanmar-payments)](https://packagist.org/packages/hakhant/myanmar-payments)
[![Downloads](https://img.shields.io/packagist/dt/hakhant/myanmar-payments)](https://packagist.org/packages/hakhant/myanmar-payments)
[![License](https://img.shields.io/github/license/hakhant21/myanmar-payments.svg)](LICENSE.md)

## Supported Providers

Myanmar payment gateway support for Laravel:

- KBZPay
- WaveMoney (Wave Pay)
- 2C2P
- AYA Pay
- MMQR support for supported providers

Common search terms: Myanmar payments, Myanmar payment gateway, KBZPay Laravel, Wave Pay Laravel, WaveMoney payment gateway, 2C2P Laravel, MMQR Laravel.

## Features

- Built for Myanmar payment integrations in Laravel apps
- Supports KBZPay, WaveMoney (Wave Pay), 2C2P, and AYA Pay
- Supports MMQR flows for supported providers
- Strict typing with `declare(strict_types=1);`
- PSR-4 autoloading
- Provider-driven architecture with `PaymentManager`, `GatewayContract`, and provider-specific gateway adapters
- Typed DTOs for payments, refunds, MMQR, and callbacks
- Provider capability contracts for refund, callback verification, and MMQR support
- Application use cases for payment creation, MMQR creation, refunds, status queries, and callback verification
- Provider adapter for 2C2P redirect checkout and refund maintenance
- Provider adapter for AYA Pay push payment, QR payment, status query, and refund
- Provider adapter for WaveMoney payment creation, MMQR creation, and callback verification
- Provider adapter for KBZPay payment, refund, callback verification, and MMQR
- Enum-based or string-based provider selection through `Provider` and `PaymentManager::provider()`
- Laravel service provider and facade integration
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

AYA_BASIC_TOKEN=
AYA_PHONE=
AYA_PASSWORD=
AYA_SERVICE_CODE=
AYA_TIME_LIMIT=
AYA_LOGIN_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/login
AYA_PUSH_PAYMENT_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestPushPayment
AYA_PUSH_PAYMENT_V2_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/v2/requestPushPayment
AYA_QUERY_PAYMENT_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/checkRequestPayment
AYA_QR_PAYMENT_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestQRPayment
AYA_REFUND_PAYMENT_URL=https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/refundPayment

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

### Package Flow

1. Configure provider credentials in `config/myanmar-payments.php`
2. Resolve a gateway through `PaymentManager` or the `MyanmarPayments` facade, or use an application use case
3. Use the high-level manager/wrapper methods for most integrations: `createPayment()`, `queryStatus()`, `createMmqr()`, `refund()`, `verifyCallback()`
4. Use capability helpers like `supportsMmqr()` when your UI or flow depends on provider features
5. Drop down to `provider()` only when you need direct access to a provider gateway
6. Handle typed DTO responses instead of raw provider payloads

## Provider Capability Matrix

| Provider | Create Payment | Query Status | Refund | Verify Callback | MMQR |
| --- | --- | --- | --- | --- | --- |
| KBZPay | Yes | Yes | Yes | Yes | Yes |
| AYA Pay | Yes | Yes | Yes | No | Yes |
| WaveMoney | Yes | No | No | Yes | Yes |
| 2C2P | Yes | Yes | Yes | Yes | No |

### Provider Selection

`PaymentManager::provider()` and `MyanmarPayments::provider()` accept either a provider string or the `Provider` enum.

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\Enums\Provider;

public function checkout(PaymentManager $payments)
{
    $gateway = $payments->provider(Provider::KBZPAY);

    // String values still work too:
    // $gateway = $payments->provider('kbzpay');
}
```

### Recommended Integration Style

For most applications, prefer the higher-level `PaymentManager` methods instead of resolving a gateway manually.

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\Enums\Provider;

public function checkout(PaymentManager $payments)
{
    return $payments->createPayment(
        new PaymentRequest(
            merchantReference: 'INV-1001',
            amount: 10000,
            currency: 'MMK',
            callbackUrl: 'https://example.com/payments/callback',
            redirectUrl: 'https://example.com/payments/return',
        ),
        Provider::KBZPAY,
    );
}
```

Use capability helpers when you need conditional behavior by provider:

```php
if ($payments->supportsMmqr(Provider::AYA)) {
    // Show MMQR option in the UI
}
```

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\Enums\Provider;

public function checkout(PaymentManager $payments)
{
    $response = $payments->provider(Provider::TWOC2P)->createPayment(
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
use Hakhant\Payments\Domain\Enums\Provider;

public function status(string $transactionId, PaymentManager $payments): array
{
    $response = $payments->queryStatus($transactionId, Provider::TWOC2P);

    return [
        'transaction_id' => $response->transactionId,
        'status' => $response->status->value,
        'provider' => $response->provider,
    ];
}
```

For 2C2P, `transactionId` is the returned payment token because the transaction-status endpoint queries by payment token.

### Refund

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\Enums\Provider;

public function refund(string $transactionId, PaymentManager $payments): array
{
    $response = $payments->refund(new RefundRequest(
        transactionId: $transactionId,
        amount: 10000,
        reason: 'Customer requested cancellation',
        metadata: [
            'reference_number' => 'provider-reference-if-required',
        ],
    ), Provider::TWOC2P);

    return [
        'refund_id' => $response->refundId,
        'status' => $response->status->value,
    ];
}
```

### Verify Callback Signature

```php
use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\Enums\Provider;
use Illuminate\Http\Request;

public function webhook(Request $request, PaymentManager $payments)
{
    $payload = new CallbackPayload(
        payload: ['payload' => (string) $request->input('payload', '')],
        signature: '',
    );

    $valid = $payments->verifyCallback($payload, Provider::TWOC2P);

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
- Published config now prefers snake_case refund keys such as `notify_url` and `idempotency_id`.
- Legacy camelCase config keys such as `notifyURL` and `idempotencyID` are still accepted at runtime for backward compatibility.
- Asynchronous refund completion callbacks and refund-status inquiry are not wrapped yet. The current package support covers refund initiation and mapping the immediate maintenance response.

### WaveMoney Notes

- `createPayment()` posts the documented form payload to WaveMoney `/payment` and returns a redirect `paymentUrl` using `/authenticate?transaction_id=...`.
- `createMmqr()` is supported through the same WaveMoney `/payment` request flow and returns `MmqrResponse::qrCode` as the generated `/authenticate?transaction_id=...` URL.
- Request hashing follows the WaveMoney formula: `time_to_live_in_seconds + merchant_id + order_id + amount + backend_result_url + merchant_reference_id` using HMAC SHA256.
- Callback verification follows the WaveMoney callback formula and treats null values as the literal string `null`, as required by docs.
- `queryStatus()` is intentionally unsupported for WaveMoney in this package because the provided docs define callback-driven status updates but no status inquiry endpoint.
- Treat callback status `PAYMENT_CONFIRMED` as success and verify hash before updating payment state.

### AYA Pay Notes

- `createPayment()` uses AYA push-payment APIs and requires `metadata['customer_phone']`.
- When `AYA_SERVICE_CODE` or `metadata['service_code']` is set, the gateway uses AYA push payment v2; otherwise it uses the v1 push endpoint.
- `queryStatus()` calls AYA `checkRequestPayment` with `externalTransactionId`.
- `createMmqr()` calls AYA `requestQRPayment` and maps `qrdata` to `MmqrResponse::qrCode`.
- `refund()` requires `RefundRequest` metadata `reference_number` because AYA needs both `externalTransactionId` and `referenceNumber`.
- AYA callback verification is not implemented yet because the provided swagger does not define a signed webhook/callback contract.

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
use Hakhant\Payments\Application\UseCases\CreateMmqr;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\Enums\Provider;

public function createMmqr(CreateMmqr $createMmqr): array
{
    $response = $createMmqr->handle(new MmqrRequest(
        merchantReference: 'MMQR-1001',
        amount: 10000,
        currency: 'MMK',
        notifyUrl: 'https://example.com/payments/mmqr/callback',
        metadata: ['invoice_no' => 'INV-1001'],
    ), Provider::AYA);

    return [
        'transaction_id' => $response->transactionId,
        'status' => $response->status->value,
        'qr_code' => $response->qrCode,
        'qr_image' => $response->qrImage,
    ];
}
```

Supported MMQR providers in this package are `KBZPay`, `AYA`, and `WaveMoney`.

Notes by provider:

- `KBZPay`: sends `kbz.payment.mmqrprecreate` using the same canonical signing helper as the rest of the KBZ gateway, with MMQR-specific `trade_type` and `notify_url` fields.
- `AYA`: uses the QR payment endpoint and maps returned `qrdata` into `MmqrResponse::qrCode`.
- `WaveMoney`: uses the same payment creation endpoint as normal checkout and returns the authenticate URL as `qr_code`.
- `2C2P`: MMQR is not implemented in this package because the current provider integration is focused on hosted checkout, status, refund maintenance, and callback verification.

For WaveMoney, `qr_code` is the Wave authenticate URL (`.../authenticate?transaction_id=...`) returned from payment initialization.

For AYA, `qr_code` is the returned `qrdata` string from `requestQRPayment`.

For KBZPay, `qr_code` is the raw EMVCo/MMQR payload returned by KBZ.

### Facade Usage

```php
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Facades\MyanmarPayments;

$response = MyanmarPayments::createPayment(
    new PaymentRequest(
        merchantReference: 'INV-2001',
        amount: 25000,
        currency: 'MMK',
        callbackUrl: 'https://example.com/payments/callback',
        redirectUrl: 'https://example.com/payments/return'
    ),
    Provider::KBZPAY,
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
