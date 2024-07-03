<?php

use Hak\Payments\Facades\Gateway;
use Hak\Payments\Methods\KbzPay;
use Hak\Payments\Methods\TwoCTwoP;
use Hak\Payments\Methods\WaveMoney;

it('can intialized new gateway by service provider and create 2c2p payment methods', function(){
    $gateway = Gateway::create("2c2p");
    expect($gateway)->toBeInstanceOf(TwoCTwoP::class);
});

it('can intialized new gateway by service provider and create unknown payment methods', function(){
    $gateway = Gateway::create('unknown');

    expect($gateway)->toBeInstanceOf(Exception::class);
});