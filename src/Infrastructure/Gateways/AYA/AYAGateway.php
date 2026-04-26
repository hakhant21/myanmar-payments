<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\AYA;

use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class AYAGateway implements CanInitiateMmqr, CanRefundPayment, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private AYAClient $client,
        private AYAMapper $mapper,
        private array $config,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $description = (string) ($request->metadata['external_additional_data']
            ?? $request->metadata['description']
            ?? $request->metadata['title']
            ?? '');

        $payload = [
            'amount' => $this->usesPushPaymentV2($request) ? $request->amount : (string) $request->amount,
            'currency' => $request->currency,
            'customerPhone' => $this->customerPhone($request),
            'externalTransactionId' => $request->merchantReference,
        ];

        if ($description !== '') {
            $payload['externalAdditionalData'] = $description;
        }

        $message = (string) ($request->metadata['message'] ?? '');
        if (! $this->usesPushPaymentV2($request) && $message !== '') {
            $payload['message'] = $message;
        }

        if ($this->usesPushPaymentV2($request)) {
            $payload['serviceCode'] = $this->serviceCode($request);

            $timeLimit = $request->metadata['time_limit'] ?? $this->config['time_limit'] ?? null;
            if (is_int($timeLimit) || is_float($timeLimit) || (is_string($timeLimit) && is_numeric($timeLimit))) {
                $payload['timelimit'] = (int) $timeLimit;
            }
        }

        return $this->mapper->toPaymentResponse(
            $this->client->requestPushPayment($payload, $this->usesPushPaymentV2($request)),
            PaymentStatus::PENDING,
        );
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        return $this->mapper->toPaymentResponse(
            $this->client->checkRequestPayment(['externalTransactionId' => $transactionId]),
            PaymentStatus::UNKNOWN,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $referenceNumber = $this->referenceNumber($request);

        return $this->mapper->toRefundResponse(
            $this->client->refundPayment([
                'externalTransactionId' => $request->transactionId,
                'referenceNumber' => $referenceNumber,
            ]),
            $referenceNumber,
            PaymentStatus::REFUNDED,
        );
    }

    public function createMmqr(MmqrRequest $request): MmqrResponse
    {
        $description = (string) ($request->metadata['external_additional_data']
            ?? $request->metadata['description']
            ?? '');

        $payload = [
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'externalTransactionId' => $request->merchantReference,
        ];

        if ($description !== '') {
            $payload['externalAdditionalData'] = $description;
        }

        return $this->mapper->toMmqrResponse(
            $this->client->requestQrPayment($payload),
            PaymentStatus::PENDING,
        );
    }

    private function customerPhone(PaymentRequest $request): string
    {
        $customerPhone = $request->metadata['customer_phone'] ?? $request->metadata['customerPhone'] ?? null;

        if (! is_string($customerPhone) || $customerPhone === '') {
            throw new ProviderException('AYA customer_phone metadata is required.');
        }

        return $customerPhone;
    }

    private function referenceNumber(RefundRequest $request): string
    {
        $referenceNumber = $request->metadata['reference_number'] ?? $request->metadata['referenceNumber'] ?? null;

        if (! is_string($referenceNumber) || $referenceNumber === '') {
            throw new ProviderException('AYA refund metadata reference_number is required.');
        }

        return $referenceNumber;
    }

    private function serviceCode(PaymentRequest $request): string
    {
        $serviceCode = $request->metadata['service_code'] ?? $request->metadata['serviceCode'] ?? $this->config['service_code'] ?? null;

        if (! is_string($serviceCode) || $serviceCode === '') {
            throw new ProviderException('AYA service_code is required for push payment v2.');
        }

        return $serviceCode;
    }

    private function usesPushPaymentV2(PaymentRequest $request): bool
    {
        return array_key_exists('service_code', $request->metadata)
            || array_key_exists('serviceCode', $request->metadata)
            || (string) ($this->config['service_code'] ?? '') !== '';
    }
}
