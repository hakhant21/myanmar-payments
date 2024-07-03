<?php

use Hak\Payments\Facades\Gateway;

beforeEach(function(){
    $this->gateway = Gateway::create("2c2p");
});

it('can get payment url with require parameters', function(){
    $url = $this->gateway->createPayment(
        1000, // amount
        random_int(100000, 999999), // invoiceNo
        "MMK", // currencyCode
        uniqid(), // nonceStr
        "https://example.com/frontend-return-url", // frontendReturnUrl
        "https://example.com/backend-return-url", // backendReturnUrl
        "test payment", // payment description
        ['test user data', 'test user data 2'] // user defined array
    );

    expect($url)->toBeString();
});