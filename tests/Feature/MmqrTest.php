<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

describe('MMQR payment flow', function (): void {
    it('creates MMQR payment and receives QR code from KBZ API', function (): void {
        Http::fake([
            'https://api.test/mmqr' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'code' => '0',
                    'msg' => 'success',
                    'merch_order_id' => 'MMQR_TEST_001',
                    'trade_status' => 'WAIT_PAY',
                    'qr_code' => '00020101021226500016...',
                    'sign' => 'FAKE_SIGN',
                    'sign_type' => 'SHA256',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $request = new MmqrRequest(
            merchantReference: 'MMQR_TEST_001',
            amount: 10000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback',
        );

        $response = $gateway->createMmqr($request);

        expect($response->transactionId)->toBe('MMQR_TEST_001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('00020101021226500016...')
            ->and($response->qrImage)->toBeNull()
            ->and($response->provider)->toBe('kbzpay');
    });

    it('creates MMQR payment with QR image included', function (): void {
        Http::fake([
            'https://api.test/mmqr' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'MMQR_IMG_001',
                    'trade_status' => 'WAIT_PAY',
                    'qr_code' => 'QR_DATA_STRING',
                    'qr_image' => 'data:image/png;base64,iVBORw0KGgoAAAANS...',
                    'sign' => 'FAKE_SIGN',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $response = $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'MMQR_IMG_001',
            amount: 5000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback',
        ));

        expect($response->qrImage)->toBe('data:image/png;base64,iVBORw0KGgoAAAANS...')
            ->and($response->qrCode)->toBe('QR_DATA_STRING');
    });

    it('handles MMQR PAY_SUCCESS status after payment completion', function (): void {
        Http::fake([
            'https://api.test/queryorder' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'MMQR_TEST_001',
                    'trade_status' => 'PAY_SUCCESS',
                    'trade_type' => 'MMQR',
                    'sign' => 'FAKE_SIGN',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $response = $gateway->queryStatus('MMQR_TEST_001');
        expect($response->status)->toBe(PaymentStatus::SUCCESS);
    });

    it('handles MMQR order expired status', function (): void {
        Http::fake([
            'https://api.test/queryorder' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'MMQR_EXP_001',
                    'trade_status' => 'ORDER_EXPIRED',
                    'trade_type' => 'MMQR',
                    'sign' => 'FAKE_SIGN',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $response = $gateway->queryStatus('MMQR_EXP_001');
        expect($response->status)->toBe(PaymentStatus::CANCELLED);
    });

    it('verifies MMQR callback webhook signature', function (): void {
        $config = config('myanmar-payments.providers.kbzpay');
        $signature = new KBZPaySignature;
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            $signature,
            $config,
        );

        $fields = [
            'appid' => $config['app_id'],
            'merch_code' => $config['merchant_code'],
            'merch_order_id' => 'MMQR_CBK_001',
            'trade_status' => 'PAY_SUCCESS',
            'trade_type' => 'MMQR',
            'total_amount' => '10000',
            'trans_currency' => 'MMK',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = $sign;

        $payload = new CallbackPayload(
            payload: ['Request' => $fields],
            signature: $sign,
        );

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('sends correct biz_content fields in MMQR precreate request', function (): void {
        $capturedPayload = null;

        Http::fake([
            'https://api.test/mmqr' => function (Request $request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'Response' => [
                        'result' => 'SUCCESS',
                        'merch_order_id' => 'MMQR_CHECK_001',
                        'trade_status' => 'WAIT_PAY',
                        'qr_code' => 'QR123',
                    ],
                ], 200);
            },
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'MMQR_CHECK_001',
            amount: 7500,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback',
            metadata: ['timeout_express' => '30m'],
        ));

        expect($capturedPayload)->not->toBeNull()
            ->and($capturedPayload['Request']['biz_content']['trade_type'])->toBe('MMQR')
            ->and($capturedPayload['Request']['biz_content']['total_amount'])->toBe('7500')
            ->and($capturedPayload['Request']['biz_content']['trans_currency'])->toBe('MMK')
            ->and($capturedPayload['Request']['method'])->toBe('kbz.payment.mmqrprecreate');
    });
});
