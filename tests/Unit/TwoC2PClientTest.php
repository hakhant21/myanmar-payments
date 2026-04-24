<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PClient;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PKeyJwt;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function twoC2pClientKeyFixture(): array
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

function twoC2pClient(array $config = []): TwoC2PClient
{
    $defaults = [
        'merchant_id' => 'JT01',
        'secret_key' => '0123456789abcdef0123456789abcdef',
        'merchant_private_key' => twoC2pClientKeyFixture()['merchant_private_key'],
        'two_c2p_public_key' => twoC2pClientKeyFixture()['two_c2p_public_key'],
        'locale' => 'en',
        'payment_description' => 'Payment',
        'endpoints' => [
            'payment_token' => 'https://sandbox.test/paymentToken',
            'transaction_status' => 'https://sandbox.test/transactionStatus',
            'refund' => 'https://sandbox.test/refund',
        ],
        'timeout' => 10,
    ];

    return new TwoC2PClient(new HttpClient, array_replace_recursive($defaults, $config));
}

beforeEach(function (): void {
    $this->jwt = new TwoC2PJwt;
});

describe('TwoC2PClient::paymentToken()', function (): void {
    it('sends a signed payload and decodes the response payload', function (): void {
        Http::fake([
            'https://sandbox.test/paymentToken' => Http::response([
                'payload' => $this->jwt->encode([
                    'webPaymentUrl' => 'https://sandbox-ui.test/token/abc',
                    'paymentToken' => 'TOKEN_123',
                    'respCode' => '0000',
                    'respDesc' => 'Success',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
        ]);

        $client = twoC2pClient();

        $response = $client->paymentToken([
            'merchantID' => 'JT01',
            'invoiceNo' => 'INV-1001',
            'description' => 'Order',
            'amount' => '1000',
            'currencyCode' => 'MMK',
        ], $this->jwt);

        expect($response['paymentToken'])->toBe('TOKEN_123')
            ->and($response['webPaymentUrl'])->toBe('https://sandbox-ui.test/token/abc');

        Http::assertSent(function (Request $request): bool {
            $token = ($request->data())['payload'] ?? null;
            if (! is_string($token)) {
                return false;
            }

            $payload = (new TwoC2PJwt)->decode($token, '0123456789abcdef0123456789abcdef');

            return $request->url() === 'https://sandbox.test/paymentToken'
                && ($payload['merchantID'] ?? null) === 'JT01'
                && ($payload['invoiceNo'] ?? null) === 'INV-1001';
        });
    });

    it('throws ProviderException when response payload is missing', function (): void {
        Http::fake([
            'https://sandbox.test/paymentToken' => Http::response([], 200),
        ]);

        $client = twoC2pClient();

        expect(fn (): array => $client->paymentToken([
            'merchantID' => 'JT01',
            'invoiceNo' => 'INV-1001',
            'description' => 'Order',
            'amount' => '1000',
            'currencyCode' => 'MMK',
        ], $this->jwt))->toThrow(ProviderException::class, '2C2P response payload is missing.');
    });
});

describe('TwoC2PClient::transactionStatus()', function (): void {
    it('sends a payment token request and decodes transaction status details', function (): void {
        Http::fake([
            'https://sandbox.test/transactionStatus' => Http::response([
                'payload' => $this->jwt->encode([
                    'invoiceNo' => 'INV-1001',
                    'paymentToken' => 'TOKEN_123',
                    'paymentResultDetails' => ['code' => '00', 'description' => 'Approved'],
                    'respCode' => '2000',
                    'respDesc' => 'Completed',
                ], '0123456789abcdef0123456789abcdef'),
            ], 200),
        ]);

        $client = twoC2pClient();

        $response = $client->transactionStatus([
            'paymentToken' => 'TOKEN_123',
            'locale' => 'en',
            'additionalInfo' => false,
        ], $this->jwt);

        expect($response['paymentResultDetails']['code'])->toBe('00')
            ->and($response['paymentToken'])->toBe('TOKEN_123');
    });
});

describe('TwoC2PClient::refund()', function (): void {
    it('sends encrypted XML and parses the decrypted payment process response', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pClientKeyFixture();

        Http::fake([
            'https://sandbox.test/refund' => Http::response(
                $jwt->encode(
                    '<PaymentProcessResponse><version>4.3</version><timeStamp>250424120000</timeStamp><respCode>42</respCode><respDesc>Refund pending</respDesc><processType>R</processType><invoiceNo>INV-1001</invoiceNo><amount>1000.00</amount><status>RP</status><referenceNo>REF-1001</referenceNo></PaymentProcessResponse>',
                    $keys['two_c2p_private_key'],
                    $keys['merchant_public_key'],
                ),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        $client = twoC2pClient();

        $response = $client->refund([
            'version' => '4.3',
            'timeStamp' => '250424120000',
            'merchantID' => 'JT01',
            'processType' => 'R',
            'invoiceNo' => 'INV-1001',
            'actionAmount' => '1000.00',
        ], $jwt);

        expect($response['referenceNo'])->toBe('REF-1001')
            ->and($response['status'])->toBe('RP')
            ->and($response['respCode'])->toBe('42');

        Http::assertSent(function (Request $request) use ($jwt, $keys): bool {
            $xml = $jwt->decode($request->body(), $keys['merchant_public_key'], $keys['two_c2p_private_key']);

            return $request->url() === 'https://sandbox.test/refund'
                && $request->header('Content-Type') === ['text/plain']
                && str_contains($xml, '<merchantID>JT01</merchantID>')
                && str_contains($xml, '<processType>R</processType>');
        });
    });

    it('adds key id headers and skips non-scalar XML fields in refund requests', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pClientKeyFixture();

        Http::fake([
            'https://sandbox.test/refund' => Http::response(
                $jwt->encode(
                    '<PaymentProcessResponse><version>4.3</version><timeStamp>250424120000</timeStamp><respCode>00</respCode><respDesc>Success</respDesc><processType>R</processType><invoiceNo>INV-1002</invoiceNo><amount>1000.00</amount><status>RF</status><referenceNo>REF-1002</referenceNo></PaymentProcessResponse>',
                    $keys['two_c2p_private_key'],
                    $keys['merchant_public_key'],
                ),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        $client = twoC2pClient(['key_id' => 'kid-123']);

        $client->refund([
            'version' => '4.3',
            'timeStamp' => '250424120000',
            'merchantID' => 'JT01',
            'processType' => 'R',
            'invoiceNo' => 'INV-1002',
            'actionAmount' => '1000.00',
            'nullableField' => null,
            'arrayField' => ['skip'],
            'objectField' => (object) ['skip' => true],
        ], $jwt);

        Http::assertSent(function (Request $request) use ($keys): bool {
            [$encodedHeader, $encodedPayload] = explode('.', $request->body());
            $header = json_decode(base64_decode(strtr($encodedHeader, '-_', '+/').str_repeat('=', (4 - strlen($encodedHeader) % 4) % 4), true), true);
            $jwe = base64_decode(strtr($encodedPayload, '-_', '+/').str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4), true);

            if (! is_array($header) || ! is_string($jwe)) {
                return false;
            }

            $xml = (new TwoC2PKeyJwt)->decode($request->body(), $keys['merchant_public_key'], $keys['two_c2p_private_key']);

            return ($header['kid'] ?? null) === 'kid-123'
                && ! str_contains($xml, 'nullableField')
                && ! str_contains($xml, 'arrayField')
                && ! str_contains($xml, 'objectField')
                && str_contains($jwe, '.');
        });
    });

    it('throws ProviderException when the refund response payload is missing', function (): void {
        Http::fake([
            'https://sandbox.test/refund' => Http::response('', 200),
        ]);

        $client = twoC2pClient();

        expect(fn (): array => $client->refund([
            'version' => '4.3',
            'timeStamp' => '250424120000',
            'merchantID' => 'JT01',
            'processType' => 'R',
            'invoiceNo' => 'INV-1001',
            'actionAmount' => '1000.00',
        ], new TwoC2PKeyJwt))->toThrow(ProviderException::class, '2C2P refund response payload is missing.');
    });

    it('throws ProviderException when the refund response XML is invalid', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pClientKeyFixture();

        Http::fake([
            'https://sandbox.test/refund' => Http::response(
                $jwt->encode('<not-xml', $keys['two_c2p_private_key'], $keys['merchant_public_key']),
                200,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        $client = twoC2pClient();

        expect(fn (): array => $client->refund([
            'version' => '4.3',
            'timeStamp' => '250424120000',
            'merchantID' => 'JT01',
            'processType' => 'R',
            'invoiceNo' => 'INV-1001',
            'actionAmount' => '1000.00',
        ], $jwt))->toThrow(ProviderException::class, '2C2P refund response XML is invalid.');
    });
});
