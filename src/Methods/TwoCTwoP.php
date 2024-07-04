<?php

namespace Hak\Payments\Methods;

use Exception;
use Hak\Payments\Traits\HasClient;
use Illuminate\Support\Facades\Http;
use Hak\Payments\Traits\HasEncryption;
use Hak\Payments\Traits\HasParameters;

class TwoCTwoP 
{
    use HasClient;
    use HasEncryption;
    use HasParameters; 

    protected array $configs;
    public function __construct()
    {
        $this->configs = config('gateway.2c2p');
    }

    public function createPayment(
        int $amount, 
        string $invoiceNo, 
        string $currencyCode, 
        string $nonceStr, 
        string $frontendReturnUrl, 
        string $backendReturnUrl, 
        string $paymentDescription, 
        array $userDefined = []
    )
    {
        $userDefined = $this->userDefinedFields($userDefined);

        $payload = $this->getPayload($amount, $invoiceNo, $nonceStr, $paymentDescription, $frontendReturnUrl, $backendReturnUrl, $userDefined);

        $this->validateData($backendReturnUrl, $this->configs['secret_key'], $this->configs['merchant_id'], $this->configs['currency_code']);

        $jwt = $this->encryptJWT($payload, $this->configs['secret_key']);

        $data['payload'] = $jwt;

        $response = $this->send($this->configs['base_url'],'paymentToken', json_encode($data));

        if(isset($response['payload'])) {
            $payload = $this->decryptJWT($response['payload'], $this->configs['secret_key']);

            return $payload->webPaymentUrl;
        } else {
            throw new Exception("Something went wrong, please try again");
        }
    }

    private function getPayload($amount, $invoiceNo, $nonceStr, $paymentDescription, $frontendReturnUrl, $backendReturnUrl, $userDefined) {
        return [
            'merchantID' => $this->configs['merchant_id'],
            'invoiceNo' => $this->getInvoiceNo($invoiceNo),
            'description' => $paymentDescription ?? "Payment for " . config("app.name"),
            'amount' => $this->getAmount($amount),
            'currencyCode' => $this->configs['currency_code'] ?? 'MMK',
            'paymentChannel' => $this->configs['payment_channel'],
            'frontendReturnUrl' => $frontendReturnUrl,
            'backendReturnUrl' => $backendReturnUrl,
            'userDefined1' => $userDefined[0] ?? '',
            'userDefined2' => $userDefined[1] ?? '',
            'userDefined3' => $userDefined[2] ?? '',
            'userDefined4' => $userDefined[3] ?? '',
            'userDefined5' => $userDefined[4] ?? '',
            'nonceStr' => $nonceStr
        ];
    }

    private function validateData($backendReturnUrl, $secretKey, $merchantId, $currencyCode)
    {
        if (!$secretKey || !$merchantId) {
            throw new Exception("Invalid Secret Key OR Invalid Merchant Id");
        }

        if (!$currencyCode) {
            throw new Exception("Invalid Currency Code");
        }

        if (!filter_var($backendReturnUrl, FILTER_VALIDATE_URL)) {
            throw  new Exception("Invalid backend URL");
        }
    }
}