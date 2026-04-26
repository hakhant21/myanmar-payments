<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Illuminate\Support\Facades\Http;

describe('MMQR feature', function (): void {
    it('creates MMQR with KBZ and returns qr_code', function (): void {
        Http::fake([
            'https://api.test/mmqr' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'code' => '0',
                    'msg' => 'success',
                    'merch_order_id' => 'MMQR_ORDER_001',
                    'trade_status' => 'WAIT_PAY',
                    'qr_code' => '00020101021226500016A0000006770101110113006699999990208MMQRMERCH53031045802MM5910Test Shop6007YANGON6304A13B',
                    'qr_image' => 'https://cdn.example.test/mmqr/MMQR_ORDER_001.png',
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
            merchantReference: 'MMQR_ORDER_001',
            amount: 3500,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback/mmqr',
            metadata: ['timeout_express' => '60m'],
        );

        $response = $gateway->createMmqr($request);

        expect($response->provider)->toBe('kbzpay')
            ->and($response->transactionId)->toBe('MMQR_ORDER_001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toContain('000201010212')
            ->and($response->qrImage)->toBe('https://cdn.example.test/mmqr/MMQR_ORDER_001.png');
    });

    it('maps PAY_SUCCESS MMQR response to SUCCESS', function (): void {
        Http::fake([
            'https://api.test/mmqr' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'MMQR_ORDER_002',
                    'trade_status' => 'PAY_SUCCESS',
                    'qr_code' => 'QR_PAY_SUCCESS',
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
            merchantReference: 'MMQR_ORDER_002',
            amount: 1500,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback/mmqr',
        ));

        expect($response->status)->toBe(PaymentStatus::SUCCESS);
    });

    it('maps unknown MMQR status to UNKNOWN', function (): void {
        Http::fake([
            'https://api.test/mmqr' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'MMQR_ORDER_003',
                    'trade_status' => 'SOMETHING_NEW',
                    'qr_code' => 'QR_UNKNOWN',
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
            merchantReference: 'MMQR_ORDER_003',
            amount: 1000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/callback/mmqr',
        ));

        expect($response->status)->toBe(PaymentStatus::UNKNOWN);
    });
});
