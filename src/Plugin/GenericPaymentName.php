<?php

namespace Tpay\Magento\Hyva\Plugin;

use Hyva\Checkout\Model\Magewire\Payment\PlaceOrderServiceProvider;

class GenericPaymentName
{
    public function beforeGetByCode(PlaceOrderServiceProvider $subject, string $code)
    {
        if (preg_match('/^generic-[0-9]+$/', $code)) {
            return ['generic'];
        }

        return [$code];
    }
}
