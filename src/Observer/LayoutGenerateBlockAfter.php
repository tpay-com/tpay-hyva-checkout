<?php

namespace Tpay\Magento\Hyva\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Layout;

class LayoutGenerateBlockAfter implements ObserverInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
        // phpcs:ignore
    ) {}

    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        /** @var Layout $layout */
        $layout = $event->getLayout();

        $block = $layout->getBlock('checkout.payment.method.generic');
        if ($block) {
            $data = $block->getData();
            $data['template'] = $block->getTemplate();
            $parentBlock = $block->getParentBlock();
            $genericMethods = $this->scopeConfig->getValue(
                'payment/tpaycom_magento2basic/openapi_settings/onsite_channels'
            );
            if (empty($genericMethods)) {
                return;
            }
            $genericMethods = explode(',', $genericMethods);
            foreach ($genericMethods as $method) {
                if (!$parentBlock->getChildBlock('generic-'.$method)) {
                    $parentBlock->addChild('generic-'.$method, get_class($block), $data);
                }
            }
        }
    }
}
