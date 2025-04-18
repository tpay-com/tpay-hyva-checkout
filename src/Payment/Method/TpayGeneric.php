<?php

namespace Tpay\Magento\Hyva\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Tpay\Magento2\Provider\ConfigurationProvider;

class TpayGeneric extends Component implements EvaluationInterface
{
    public function __construct(
        private readonly SessionCheckout $sessionCheckout,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ConfigurationProvider $tPayConfigProvider,
        // phpcs:ignore
    ) {}

    public function mount(): void
    {
        $data = $this->sessionCheckout->getQuote()->getPayment()->getAdditionalInformation();
    }

    public function updated($value, $name)
    {
        $quote = $this->sessionCheckout->getQuote();
        $quote->getPayment()->setAdditionalInformation('group', null);
        $quote->getPayment()->setAdditionalInformation('accept_tos', true);
        $quote->getPayment()->unsAdditionalInformation('card_save');
        $quote->getPayment()->unsAdditionalInformation('card_id');
        $quote->getPayment()->unsAdditionalInformation('card_data');
        $quote->getPayment()->unsAdditionalInformation('card_vendor');
        $quote->getPayment()->unsAdditionalInformation('short_code');
        $quote->getPayment()->unsAdditionalInformation('blik_code');
        $this->quoteRepository->save($quote);

        return $value;
    }

    public function getTerms()
    {
        return $this->tPayConfigProvider->getTermsURL();
    }

    public function getRegulations()
    {
        return $this->tPayConfigProvider->getRegulationsURL();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (!preg_match('/^generic-[0-9]+$/', $this->sessionCheckout->getQuote()->getPayment()->getMethod())) {
            return $resultFactory->createSuccess();
        }

        $this->updated('selected', true);

        return $resultFactory->createSuccess();
    }
}
