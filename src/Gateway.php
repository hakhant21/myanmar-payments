<?php

namespace Hak\Payments;

use Exception;
use Hak\Payments\Methods\TwoCTwoP;

class Gateway 
{
     public function create($method)
     {
         return match($method) {
            "2c2p" => new TwoCTwoP(),
            default => new Exception('Payment method not found'),
         };
     }
}