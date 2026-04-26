<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PClient;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PKeyJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function twoC2pGatewayKeyFixture(): array
{
    static $fixture;

    if ($fixture !== null) {
        return $fixture;
    }

    $merchant = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $twoC2p = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    if ($merchant === false || $twoC2p === false) {
        throw new RuntimeException('Unable to generate RSA keys for 2C2P tests.');
    }

    openssl_pkey_export($merchant, $merchantPrivateKey);
    openssl_pkey_export($twoC2p, $twoC2pPrivateKey);

    $merchantPublicKey = openssl_pkey_get_details($merchant)['key'] ?? null;
    $twoC2pPublicKey = openssl_pkey_get_details($twoC2p)['key'] ?? null;

    if (! is_string($merchantPublicKey) || ! is_string($twoC2pPublicKey)) {
        throw new RuntimeException('Unable to export RSA public keys for 2C2P tests.');
    }

    return $fixture = [
        'merchant_private_key' => $merchantPrivateKey,
        'merchant_public_key' => $merchantPublicKey,
        'two_c2p_private_key' => $twoC2pPrivateKey,
        'two_c2p_public_key' => $twoC2pPublicKey,
    ];
}

function twoC2pGatewayConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'merchant_id' => 'JT01',
        'secret_key' => '0123456789abcdef0123456789abcdef',
        'merchant_private_key' => twoC2pGatewayKeyFixture()['merchant_private_key'],
        'two_c2p_public_key' => twoC2pGatewayKeyFixture()['two_c2p_public_key'],
        'locale' => 'en',
        'payment_description' => 'Payment',
        'maintenance_version' => '4.3',
        'endpoints' => [
            'payment_token' => 'https://sandbox.test/paymentToken',
            'transaction_status' => 'https://sandbox.test/transactionStatus',
            'refund' => 'https://sandbox.test/refund',
        ],
        'timeout' => 10,
    ], $overrides);
}

function buildTwoC2pGateway(array $config = []): array
{
    $finalConfig = twoC2pGatewayConfig($config);

    $gateway = new TwoC2PGateway(
        new TwoC2PClient(new HttpClient, $finalConfig),
        new TwoC2PMapper,
        new TwoC2PJwt,
        new TwoC2PKeyJwt,
        $finalConfig,
    );

    return [$gateway, $finalConfig];
}

describe('TwoC2PGateway::createPayment()', function (): void {
    it('creates a payment token and maps the hosted payment page response', function (): void {
        $jwt = new TwoC2PJwt;

        Http::fake([
            'https://sandbox.test/paymentToken' => Http::response([
                'payload' => $jwt->encode([
                    'webPaymentUrl' => 'https://sandbox-ui.test/token/abc',
                    'paymentToken' => 'TOKEN_123',
                    'respCode' => '0000',
                    'respDesc' => 'Success',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
        ]);

        [$gateway] = buildTwoC2pGateway();

        $response = $gateway->createPayment(new PaymentRequest(
            merchantReference: 'INV-1001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://merchant.test/callback',
            redirectUrl: 'https://merchant.test/return',
            metadata: ['description' => 'Order INV-1001'],
        ));

        expect($response)->toBeInstanceOf(PaymentResponse::class)
            ->and($response->provider)->toBe('2c2p')
            ->and($response->transactionId)->toBe('TOKEN_123')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->paymentUrl)->toBe('https://sandbox-ui.test/token/abc');
    });

    it('passes supported metadata fields through to the payment token request', function (): void {
        $jwt = new TwoC2PJwt;

        Http::fake([
            'https://sandbox.test/paymentToken' => Http::response([
                'payload' => $jwt->encode([
                    'webPaymentUrl' => 'https://sandbox-ui.test/token/abc',
                    'paymentToken' => 'TOKEN_123',
                    'respCode' => '0000',
                    'respDesc' => 'Success',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
        ]);

        [$gateway] = buildTwoC2pGateway();

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'INV-1002',
            amount: 1500,
            currency: 'MMK',
            callbackUrl: 'https://merchant.test/callback',
            redirectUrl: 'https://merchant.test/return',
            metadata: [
                'paymentChannel' => ['WEBPAY'],
                'agentChannel' => ['CHANNEL'],
                'request3DS' => 'Y',
                'nonceStr' => 'nonce',
                'paymentExpiry' => '2026-12-31 23:59:59',
                'userDefined1' => 'one',
                'userDefined2' => 'two',
                'userDefined3' => 'three',
                'userDefined4' => 'four',
                'userDefined5' => 'five',
                'immediatePayment' => true,
                'iframeMode' => false,
                'idempotencyID' => 'idem-1002',
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            $token = ($request->data())['payload'] ?? null;
            if (! is_string($token)) {
                return false;
            }

            $payload = (new TwoC2PJwt)->decode($token, '0123456789abcdef0123456789abcdef');

            return ($payload['paymentChannel'] ?? null) === ['WEBPAY']
                && ($payload['agentChannel'] ?? null) === ['CHANNEL']
                && ($payload['request3DS'] ?? null) === 'Y'
                && ($payload['nonceStr'] ?? null) === 'nonce'
                && ($payload['paymentExpiry'] ?? null) === '2026-12-31 23:59:59'
                && ($payload['userDefined1'] ?? null) === 'one'
                && ($payload['userDefined2'] ?? null) === 'two'
                && ($payload['userDefined3'] ?? null) === 'three'
                && ($payload['userDefined4'] ?? null) === 'four'
                && ($payload['userDefined5'] ?? null) === 'five'
                && ($payload['immediatePayment'] ?? null) === true
                && ($payload['iframeMode'] ?? null) === false
                && ($payload['idempotencyID'] ?? null) === 'idem-1002';
        });
    });
});

describe('TwoC2PGateway::refund()', function (): void {
    it('creates a refund request via the maintenance API and maps a refunded response', function (): void {
        $keyJwt = new TwoC2PKeyJwt;
        $keys = twoC2pGatewayKeyFixture();

        Http::fake([
            'https://sandbox.test/refund' => Http::response(
                $keyJwt->encode(
                    '<PaymentProcessResponse><version>4.3</version><timeStamp>250424120000</timeStamp><respCode>00</respCode><respDesc>Success</respDesc><processType>R</processType><invoiceNo>INV-1001</invoiceNo><amount>1000.00</amount><status>RF</status><referenceNo>REF-1001</referenceNo></PaymentProcessResponse>',
                    $keys['two_c2p_private_key'],
                    $keys['merchant_public_key'],
                ),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        [$gateway] = buildTwoC2pGateway();

        $response = $gateway->refund(new RefundRequest(
            transactionId: 'INV-1001',
            amount: 1000,
            reason: 'customer_request',
        ));

        expect($response->provider)->toBe('2c2p')
            ->and($response->refundId)->toBe('REF-1001')
            ->and($response->status)->toBe(PaymentStatus::REFUNDED);

        Http::assertSent(function (Request $request) use ($keyJwt, $keys): bool {
            $xml = $keyJwt->decode($request->body(), $keys['merchant_public_key'], $keys['two_c2p_private_key']);

            return $request->url() === 'https://sandbox.test/refund'
                && str_contains($xml, '<processType>R</processType>')
                && str_contains($xml, '<invoiceNo>INV-1001</invoiceNo>')
                && str_contains($xml, '<actionAmount>1000.00</actionAmount>')
                && str_contains($xml, '<userDefined1>customer_request</userDefined1>');
        });
    });

    it('passes optional maintenance fields configured for refunds', function (): void {
        $keyJwt = new TwoC2PKeyJwt;
        $keys = twoC2pGatewayKeyFixture();

        Http::fake([
            'https://sandbox.test/refund' => Http::response(
                $keyJwt->encode(
                    '<PaymentProcessResponse><version>4.3</version><timeStamp>250424120000</timeStamp><respCode>42</respCode><respDesc>Pending</respDesc><processType>R</processType><invoiceNo>INV-1002</invoiceNo><amount>1000.00</amount><status>RP</status><referenceNo>REF-1002</referenceNo></PaymentProcessResponse>',
                    $keys['two_c2p_private_key'],
                    $keys['merchant_public_key'],
                ),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        [$gateway] = buildTwoC2pGateway([
            'notifyURL' => 'https://merchant.test/refund-callback',
            'idempotencyID' => 'refund-idem-1',
            'bankCode' => 'BANK1',
            'accountName' => 'Customer Name',
            'accountNumber' => '1234567890',
            'userDefined2' => 'two',
            'userDefined3' => 'three',
            'userDefined4' => 'four',
            'userDefined5' => 'five',
        ]);

        $response = $gateway->refund(new RefundRequest(
            transactionId: 'INV-1002',
            amount: 1000,
        ));

        expect($response->status)->toBe(PaymentStatus::PENDING);

        Http::assertSent(function (Request $request) use ($keyJwt, $keys): bool {
            $xml = $keyJwt->decode($request->body(), $keys['merchant_public_key'], $keys['two_c2p_private_key']);

            return str_contains($xml, '<notifyURL>https://merchant.test/refund-callback</notifyURL>')
                && str_contains($xml, '<idempotencyID>refund-idem-1</idempotencyID>')
                && str_contains($xml, '<bankCode>BANK1</bankCode>')
                && str_contains($xml, '<accountName>Customer Name</accountName>')
                && str_contains($xml, '<accountNumber>1234567890</accountNumber>')
                && str_contains($xml, '<userDefined2>two</userDefined2>')
                && str_contains($xml, '<userDefined3>three</userDefined3>')
                && str_contains($xml, '<userDefined4>four</userDefined4>')
                && str_contains($xml, '<userDefined5>five</userDefined5>');
        });
    });

    it('accepts snake_case aliases for payment metadata and refund config', function (): void {
        $jwt = new TwoC2PJwt;
        $keyJwt = new TwoC2PKeyJwt;
        $keys = twoC2pGatewayKeyFixture();

        Http::fake([
            'https://sandbox.test/paymentToken' => Http::response([
                'payload' => $jwt->encode([
                    'webPaymentUrl' => 'https://sandbox-ui.test/token/alias',
                    'paymentToken' => 'TOKEN_ALIAS',
                    'respCode' => '0000',
                    'respDesc' => 'Success',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
            'https://sandbox.test/refund' => Http::response(
                $keyJwt->encode(
                    '<PaymentProcessResponse><version>4.3</version><timeStamp>250424120000</timeStamp><respCode>42</respCode><respDesc>Pending</respDesc><processType>R</processType><invoiceNo>INV-ALIAS</invoiceNo><amount>1000.00</amount><status>RP</status><referenceNo>REF-ALIAS</referenceNo></PaymentProcessResponse>',
                    $keys['two_c2p_private_key'],
                    $keys['merchant_public_key'],
                ),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        [$gateway] = buildTwoC2pGateway([
            'notify_url' => 'https://merchant.test/refund-alias',
            'idempotency_id' => 'refund-idem-alias',
            'bank_code' => 'BANK2',
            'account_name' => 'Alias Customer',
            'account_number' => '999000111',
            'user_defined_2' => 'alias-two',
            'user_defined_3' => 'alias-three',
            'user_defined_4' => 'alias-four',
            'user_defined_5' => 'alias-five',
        ]);

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'INV-ALIAS',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://merchant.test/callback',
            redirectUrl: 'https://merchant.test/return',
            metadata: [
                'payment_channel' => ['WEBPAY'],
                'agent_channel' => ['CHANNEL'],
                'request_3ds' => 'Y',
                'nonce_str' => 'snake-nonce',
                'payment_expiry' => '2026-12-31 23:59:59',
                'user_defined_1' => 'alias-one',
                'user_defined_2' => 'alias-two',
                'user_defined_3' => 'alias-three',
                'user_defined_4' => 'alias-four',
                'user_defined_5' => 'alias-five',
                'immediate_payment' => true,
                'iframe_mode' => false,
                'idempotency_id' => 'idem-alias',
            ],
        ));

        $gateway->refund(new RefundRequest(
            transactionId: 'INV-ALIAS',
            amount: 1000,
        ));

        Http::assertSent(function (Request $request) use ($keyJwt, $keys): bool {
            if ($request->url() !== 'https://sandbox.test/paymentToken') {
                return false;
            }

            $token = ($request->data())['payload'] ?? null;
            if (! is_string($token)) {
                return false;
            }

            $payload = (new TwoC2PJwt)->decode($token, '0123456789abcdef0123456789abcdef');

            return ($payload['paymentChannel'] ?? null) === ['WEBPAY']
                && ($payload['agentChannel'] ?? null) === ['CHANNEL']
                && ($payload['request3DS'] ?? null) === 'Y'
                && ($payload['nonceStr'] ?? null) === 'snake-nonce'
                && ($payload['paymentExpiry'] ?? null) === '2026-12-31 23:59:59'
                && ($payload['userDefined1'] ?? null) === 'alias-one'
                && ($payload['userDefined2'] ?? null) === 'alias-two'
                && ($payload['userDefined3'] ?? null) === 'alias-three'
                && ($payload['userDefined4'] ?? null) === 'alias-four'
                && ($payload['userDefined5'] ?? null) === 'alias-five'
                && ($payload['immediatePayment'] ?? null) === true
                && ($payload['iframeMode'] ?? null) === false
                && ($payload['idempotencyID'] ?? null) === 'idem-alias';
        });

        Http::assertSent(function (Request $request) use ($keyJwt, $keys): bool {
            if ($request->url() !== 'https://sandbox.test/refund') {
                return false;
            }

            $xml = $keyJwt->decode($request->body(), $keys['merchant_public_key'], $keys['two_c2p_private_key']);

            return str_contains($xml, '<notifyURL>https://merchant.test/refund-alias</notifyURL>')
                && str_contains($xml, '<idempotencyID>refund-idem-alias</idempotencyID>')
                && str_contains($xml, '<bankCode>BANK2</bankCode>')
                && str_contains($xml, '<accountName>Alias Customer</accountName>')
                && str_contains($xml, '<accountNumber>999000111</accountNumber>')
                && str_contains($xml, '<userDefined2>alias-two</userDefined2>')
                && str_contains($xml, '<userDefined3>alias-three</userDefined3>')
                && str_contains($xml, '<userDefined4>alias-four</userDefined4>')
                && str_contains($xml, '<userDefined5>alias-five</userDefined5>');
        });
    });
});

describe('TwoC2PGateway::queryStatus()', function (): void {
    it('queries transaction status using payment token', function (): void {
        $jwt = new TwoC2PJwt;

        Http::fake([
            'https://sandbox.test/transactionStatus' => Http::response([
                'payload' => $jwt->encode([
                    'invoiceNo' => 'INV-1001',
                    'paymentToken' => 'TOKEN_123',
                    'paymentResultDetails' => ['code' => '00', 'description' => 'Approved'],
                    'respCode' => '2000',
                    'respDesc' => 'Completed',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
        ]);

        [$gateway] = buildTwoC2pGateway();

        $response = $gateway->queryStatus('TOKEN_123');

        expect($response->status)->toBe(PaymentStatus::SUCCESS)
            ->and($response->transactionId)->toBe('TOKEN_123');
    });
});

describe('TwoC2PGateway::verifyCallback()', function (): void {
    it('returns true for a valid signed callback payload', function (): void {
        [$gateway] = buildTwoC2pGateway();
        $jwt = new TwoC2PJwt;

        $payload = new CallbackPayload(
            payload: [
                'payload' => $jwt->encode([
                    'merchantID' => 'JT01',
                    'invoiceNo' => 'INV-1001',
                    'paymentID' => 'ccpp_1234',
                    'respCode' => '0000',
                ], '0123456789abcdef0123456789abcdef'),
            ],
            signature: '',
        );

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('returns true when the signed callback token is provided via signature fallback', function (): void {
        [$gateway] = buildTwoC2pGateway();
        $jwt = new TwoC2PJwt;

        $payload = new CallbackPayload(
            payload: [],
            signature: $jwt->encode([
                'merchantID' => 'JT01',
                'invoiceNo' => 'INV-1001',
                'respCode' => '0000',
            ], '0123456789abcdef0123456789abcdef'),
        );

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('returns false for a callback signed with the wrong secret', function (): void {
        [$gateway] = buildTwoC2pGateway();
        $jwt = new TwoC2PJwt;

        $payload = new CallbackPayload(
            payload: [
                'payload' => $jwt->encode([
                    'merchantID' => 'JT01',
                    'invoiceNo' => 'INV-1001',
                    'respCode' => '0000',
                ], 'fedcba9876543210fedcba9876543210'),
            ],
            signature: '',
        );

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('returns false when callback merchant does not match config', function (): void {
        [$gateway] = buildTwoC2pGateway();
        $jwt = new TwoC2PJwt;

        $payload = new CallbackPayload(
            payload: [
                'payload' => $jwt->encode([
                    'merchantID' => 'OTHER',
                    'invoiceNo' => 'INV-1001',
                    'respCode' => '0000',
                ], '0123456789abcdef0123456789abcdef'),
            ],
            signature: '',
        );

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('returns false when callback contains neither payload token nor signature', function (): void {
        [$gateway] = buildTwoC2pGateway();

        $payload = new CallbackPayload(payload: [], signature: '');

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });
});
