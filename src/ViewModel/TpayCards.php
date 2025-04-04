<?php

namespace Tpay\Magento\Hyva\ViewModel;

use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Tpay\Magento2\Provider\ConfigurationProvider;

class TpayCards implements ArgumentInterface
{
    public function __construct(
        private readonly SessionCheckout $sessionCheckout,
        private readonly ConfigurationProvider $tPayConfigProvider,
        // phpcs:ignore
    ) {}

    public function canSaveCC(): bool
    {
        return !empty($this->sessionCheckout->getQuote()->getCustomerId())
            && $this->tPayConfigProvider->getCardSaveEnabled();
    }

    public function getPublicKey(): string
    {
        return $this->tPayConfigProvider->getRSAKey() ?? '';
    }
}
