<?php

namespace Tpay\Magento\Hyva\Payment\Method;

use Exception;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Tpay\Magento2\Model\ApiFacade\TpayConfig\CardConfigFacade;
use Tpay\Magento2\Provider\ConfigurationProvider;

class TpayCard extends Component implements EvaluationInterface
{
    public int|string $saved = '';
    public string $token = '';
    public string $suffix = '';
    public string $type = '';
    public bool $save = false;

    public function __construct(
        private readonly SessionCheckout $sessionCheckout,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ConfigurationProvider $tpayConfigProvider,
        private readonly CardConfigFacade $cardConfigFacade,
        // phpcs:ignore
    ) {}

    public function mount(): void
    {
        $data = $this->sessionCheckout->getQuote()->getPayment()->getAdditionalInformation();
        $this->token = $data['card_data'] ?? '';
        $this->saved = $data['card_id'] ?? 'new_card';
    }

    public function updated($value, $name)
    {
        $quote = $this->sessionCheckout->getQuote();
        $quote->getPayment()->setAdditionalInformation('accept_tos', true);
        $quote->getPayment()->setAdditionalInformation('card_data', 'new_card' == $this->saved ? $this->token : null);
        $quote->getPayment()->setAdditionalInformation('card_id', 'new_card' == $this->saved ? null : $this->saved);
        if ($this->save && 'new_card' == $this->saved) {
            $quote->getPayment()->setAdditionalInformation('card_save', true);
            $quote->getPayment()->setAdditionalInformation('short_code', $this->suffix);
            $type = explode('-', $this->type);
            $quote->getPayment()->setAdditionalInformation('card_vendor', $type[1]);
        } else {
            $quote->getPayment()->unsAdditionalInformation('card_save');
        }
        $this->quoteRepository->save($quote);

        return $value;
    }

    public function canSaveCC(): bool
    {
        return !empty($this->sessionCheckout->getQuote()->getCustomerId())
            && $this->tpayConfigProvider->getCardSaveEnabled();
    }

    public function getTerms(): string
    {
        return $this->tpayConfigProvider->getTermsURL();
    }

    public function getRegulations()
    {
        return $this->tpayConfigProvider->getRegulationsURL();
    }

    public function preserveToken(string $token, string $type, string $suffix, bool $save): void
    {
        $this->token = $token;
        $this->type = $type;
        $this->suffix = $suffix;
        $this->save = $save;
        $this->updated($token, 'token');
    }

    public function getTokens(): array
    {
        try {
            $config = $this->cardConfigFacade->getConfig();

            return $config['tpaycards']['payment']['customerTokens'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ('Tpay_Magento2_Cards' != $this->sessionCheckout->getQuote()->getPayment()->getMethod()) {
            return $resultFactory->createSuccess();
        }

        if (empty($this->token) && 'new_card' == $this->saved) {
            $errorMessageEvent = $resultFactory->createErrorMessageEvent(__('No card data'))
                ->withCustomEvent('payment:method:error');

            return $resultFactory->createValidation('validateTpayCardData')->withFailureResult($errorMessageEvent);
        }

        return $resultFactory->createSuccess();
    }
}
