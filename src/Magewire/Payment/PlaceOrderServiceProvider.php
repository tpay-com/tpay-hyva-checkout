<?php

namespace Tpay\Magento\Hyva\Magewire\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Tpay\Magento2\Model\TpayPayment;

class PlaceOrderServiceProvider extends AbstractPlaceOrderService
{
    public function __construct(
        CartManagementInterface $cartManagement,
        private readonly TpayPayment $tPay,
    ) {
        parent::__construct($cartManagement);
    }

    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return $this->tPay->getPaymentRedirectUrl();
    }
}
