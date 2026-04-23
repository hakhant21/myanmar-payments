<?php

declare(strict_types=1);

use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPaySignature;

beforeEach(function (): void {
    $this->signature = new KBZPaySignature;
    $this->secret = 'test_secret_key';
});

describe('KBZPaySignature::sign()', function (): void {
    it('produces uppercase SHA256 hash', function (): void {
        $fields = ['appid' => 'app123', 'merch_code' => 'MERCH'];
        $result = $this->signature->sign($fields, $this->secret);
        expect($result)->toMatch('/^[A-F0-9]{64}$/');
    });

    it('sorts parameters by ASCII key before signing', function (): void {
        $fieldsA = ['z_key' => 'z', 'a_key' => 'a'];
        $fieldsB = ['a_key' => 'a', 'z_key' => 'z'];
        expect($this->signature->sign($fieldsA, $this->secret))
            ->toBe($this->signature->sign($fieldsB, $this->secret));
    });

    it('excludes sign field from signing', function (): void {
        $withSign = ['appid' => 'app123', 'sign' => 'ignore_me'];
        $withoutSign = ['appid' => 'app123'];
        expect($this->signature->sign($withSign, $this->secret))
            ->toBe($this->signature->sign($withoutSign, $this->secret));
    });

    it('excludes sign_type field from signing', function (): void {
        $with = ['appid' => 'app123', 'sign_type' => 'SHA256'];
        $without = ['appid' => 'app123'];
        expect($this->signature->sign($with, $this->secret))
            ->toBe($this->signature->sign($without, $this->secret));
    });

    it('excludes null values from signing', function (): void {
        $with = ['appid' => 'app123', 'empty' => null];
        $without = ['appid' => 'app123'];
        expect($this->signature->sign($with, $this->secret))
            ->toBe($this->signature->sign($without, $this->secret));
    });

    it('excludes empty string values from signing', function (): void {
        $with = ['appid' => 'app123', 'empty' => ''];
        $without = ['appid' => 'app123'];
        expect($this->signature->sign($with, $this->secret))
            ->toBe($this->signature->sign($without, $this->secret));
    });

    it('excludes array values (JSONArray fields) from signing', function (): void {
        $with = ['appid' => 'app123', 'refund_info' => ['item1']];
        $without = ['appid' => 'app123'];
        expect($this->signature->sign($with, $this->secret))
            ->toBe($this->signature->sign($without, $this->secret));
    });

    it('appends &key=SECRET at the end of the signing string', function (): void {
        $fields = ['appid' => 'myapp'];
        $expectedBase = 'appid=myapp&key='.$this->secret;
        $expected = strtoupper(hash('sha256', $expectedBase));
        expect($this->signature->sign($fields, $this->secret))->toBe($expected);
    });

    it('produces deterministic output for same input', function (): void {
        $fields = ['amount' => '1000', 'merch_code' => 'MERCH', 'appid' => 'APP'];
        expect($this->signature->sign($fields, $this->secret))
            ->toBe($this->signature->sign($fields, $this->secret));
    });
});

describe('KBZPaySignature::verify()', function (): void {
    it('returns true for a valid signature', function (): void {
        $fields = ['appid' => 'app123', 'merch_code' => 'MERCH', 'amount' => '5000'];
        $sign = $this->signature->sign($fields, $this->secret);
        expect($this->signature->verify($fields, $sign, $this->secret))->toBeTrue();
    });

    it('returns true for a lowercase provided signature (case-insensitive)', function (): void {
        $fields = ['appid' => 'app123'];
        $sign = strtolower($this->signature->sign($fields, $this->secret));
        expect($this->signature->verify($fields, $sign, $this->secret))->toBeTrue();
    });

    it('returns false when a field value is tampered', function (): void {
        $fields = ['appid' => 'app123', 'amount' => '5000'];
        $sign = $this->signature->sign($fields, $this->secret);
        $tampered = ['appid' => 'app123', 'amount' => '9999'];
        expect($this->signature->verify($tampered, $sign, $this->secret))->toBeFalse();
    });

    it('returns false when the secret is wrong', function (): void {
        $fields = ['appid' => 'app123'];
        $sign = $this->signature->sign($fields, $this->secret);
        expect($this->signature->verify($fields, $sign, 'wrong_secret'))->toBeFalse();
    });

    it('returns false when extra fields are injected', function (): void {
        $fields = ['appid' => 'app123'];
        $sign = $this->signature->sign($fields, $this->secret);
        $injected = ['appid' => 'app123', 'extra' => 'injected'];
        expect($this->signature->verify($injected, $sign, $this->secret))->toBeFalse();
    });
});
