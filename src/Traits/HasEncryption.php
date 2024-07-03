<?php

namespace Hak\Payments\Traits;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

trait HasEncryption
{
    public function encryptJWT(array $payload, $secretKey)
    {
        return JWT::encode($payload, $secretKey, 'HS256');
    }

    public function decryptJWT($token, $secretKey)
    {
        return JWT::decode($token, new Key($secretKey, 'HS256'));
    }
}