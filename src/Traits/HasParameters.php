<?php

namespace Hak\Payments\Traits;

trait HasParameters
{
    private function userDefinedFields($userDefined)
    {
        if (count($userDefined) > 5) {
            throw new Exception("only 5 User defined values can be existed");
        }

        $keys = range(1,5);
        for($index = 0; $index < count($keys); $index++) {
            $userDefined[$index] = $userDefined[$index] ?? "";
        }

        return $userDefined;
    }

    private function getInvoiceNo($invoiceNo)
    {
        return str_pad($invoiceNo, 12, "0", STR_PAD_LEFT);
    }

    private function getAmount($amount)
    {
        $real_amount = sprintf('%.2f', $amount);

        $amount = str_replace('.', '', $real_amount);

        return str_pad($amount, 12, '0', STR_PAD_LEFT);
    }
}