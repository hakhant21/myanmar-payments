<?php

declare(strict_types=1);

use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function unitClient(array $config = []): KBZPayClient
{
    $defaults = [
        'app_id' => 'TEST_APP',
        'merchant_code' => 'TEST_MERCH',
        'secret' => 'TEST_SECRET',
        'notify_url' => 'https://example.test/callback',
        'endpoints' => [
            'precreate' => 'https://api.test/precreate',
            'queryorder' => 'https://api.test/queryorder',
            'refund' => 'https://api.test/refund',
            'mmqr' => 'https://api.test/precreate',
        ],
        'versions' => [
            'precreate' => '1.0',
            'queryorder' => '3.0',
            'refund' => '1.0',
            'mmqr' => '1.0',
        ],
        'timeout' => 10,
    ];

    return new KBZPayClient(new HttpClient, array_replace_recursive($defaults, $config));
}

beforeEach(function (): void {
    $this->signature = new KBZPaySignature;
});

describe('KBZPayClient::precreate()', function (): void {
    it('sends POST to precreate URL with correct Request envelope', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $result = $client->precreate([
            'appid' => 'APP',
            'merch_code' => 'MERCH',
            'total_amount' => '1000',
        ], $this->signature);

        expect($result['Response']['result'])->toBe('SUCCESS');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $payload = $body['Request'] ?? [];

            return $request->url() === 'https://api.test/precreate'
                && ($payload['method'] ?? null) === 'kbz.payment.precreate'
                && ($payload['sign_type'] ?? null) === 'SHA256'
                && ($payload['version'] ?? null) === '1.0'
                && isset($payload['notify_url'])
                && isset($payload['sign'])
                && isset($payload['nonce_str']);
        });
    });

    it('uses the passed notify_url for precreate requests', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $client->precreate(['appid' => 'APP'], $this->signature, 'https://example.test/runtime-callback');

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];

            return ($payload['notify_url'] ?? null) === 'https://example.test/runtime-callback';
        });
    });

    it('generates unique nonce_str for each call', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $client->precreate(['appid' => 'APP'], $this->signature);
        $client->precreate(['appid' => 'APP'], $this->signature);

        $requests = Http::recorded();

        $firstPayload = $requests[0][0]->data()['Request'];
        $secondPayload = $requests[1][0]->data()['Request'];

        expect($firstPayload['nonce_str'])->not->toBe($secondPayload['nonce_str']);
    });
});

describe('KBZPayClient::queryOrder()', function (): void {
    it('sends POST to queryorder URL with expected method and version', function (): void {
        Http::fake([
            'https://api.test/queryorder' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $client->queryOrder(['appid' => 'APP', 'merch_order_id' => 'ORD001'], $this->signature);

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];

            return $request->url() === 'https://api.test/queryorder'
                && ($payload['method'] ?? null) === 'kbz.payment.queryorder'
                && ($payload['version'] ?? null) === '3.0'
                && ! array_key_exists('notify_url', $payload);
        });
    });
});

describe('KBZPayClient::refund()', function (): void {
    it('sends POST to refund URL with expected method', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $client->refund(['appid' => 'APP', 'refund_amount' => '500'], $this->signature);

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];

            return $request->url() === 'https://api.test/refund'
                && ($payload['method'] ?? null) === 'kbz.payment.refund';
        });
    });
});

describe('KBZPayClient::mmqrPrecreate()', function (): void {
    it('uses precreate endpoint and method for MMQR requests', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient();
        $client->mmqrPrecreate([
            'appid' => 'APP',
            'trade_type' => 'PAY_BY_QRCODE',
            'total_amount' => '2000',
            'notify_url' => 'https://example.test/mmqr-callback',
        ], $this->signature);

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];
            $biz = $payload['biz_content'] ?? [];

            return $request->url() === 'https://api.test/precreate'
                && ($payload['method'] ?? null) === 'kbz.payment.precreate'
                && ($payload['notify_url'] ?? null) === 'https://example.test/mmqr-callback'
                && ! array_key_exists('notify_url', is_array($biz) ? $biz : []);
        });
    });

    it('uses configured client certificate options for refunds when provided', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response(['Response' => ['result' => 'SUCCESS']], 200),
        ]);

        $client = unitClient([
            'client_certificate_path' => '/tmp/kbzpay-cert.pem',
            'client_certificate_key_path' => '/tmp/kbzpay-key.pem',
            'client_certificate_key_passphrase' => 'secret-pass',
        ]);

        $client->refund(['appid' => 'APP', 'refund_request_no' => 'REF001'], $this->signature);

        $request = Http::recorded()[0][0];

        expect($request->url())->toBe('https://api.test/refund');
    });
});
