<?php

namespace Tpay\Magento\Hyva\Payment\Method;

use Exception;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Tpay\Magento2\Model\AliasRepository;
use Tpay\Magento2\Model\ApiFacade\TpayConfig\ConfigFacade;
use Tpay\Magento2\Provider\ConfigurationProvider;

class Tpay extends Component implements EvaluationInterface
{
    public int $group = 0;
    public string $blikCode = '';
    public bool $blikAlias = false;
    private array $config = [];

    public function __construct(
        private readonly SessionCheckout $sessionCheckout,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ConfigurationProvider $tPayConfigProvider,
        private readonly ConfigFacade $configFacade,
        private readonly AliasRepository $aliasRepository,
    ) {
        try {
            $this->config = $this->configFacade->getConfig();
        } catch (Exception $e) {
        }
    }

    public function mount(): void
    {
        $data = $this->sessionCheckout->getQuote()->getPayment()->getAdditionalInformation();
        $this->group = (int) ($data['group'] ?? 0);
        $this->blikCode = $data['blik_code'] ?? '';
        $this->blikAlias = 'on' === $data['blik_alias'];
    }

    public function updated($value, $name)
    {
        $quote = $this->sessionCheckout->getQuote();
        $quote->getPayment()->setAdditionalInformation('group', $this->group);
        $quote->getPayment()->setAdditionalInformation('blik_code', $this->blikCode);
        $quote->getPayment()->setAdditionalInformation('blik_alias', $this->blikAlias ? 'on' : '');
        $quote->getPayment()->setAdditionalInformation('accept_tos', true);
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

    public function selectGroup(int $group)
    {
        $this->group = $group;
        $this->updated('group', $group);
    }

    public function getUseBlik()
    {
        return $this->config['tpay']['payment']['blikStatus'] ?? false;
    }

    public function getGroups()
    {
        return $this->config['tpay']['payment']['groups'] ?? [];
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ('Tpay_Magento2' != $this->sessionCheckout->getQuote()->getPayment()->getMethod()) {
            return $resultFactory->createSuccess();
        }

        if (!empty($this->blikCode) && 6 != strlen($this->blikCode)) {
            $errorMessageEvent = $resultFactory->createErrorMessageEvent(__('Invalid BLIK code'))
                ->withCustomEvent('payment:method:error');

            return $resultFactory->createValidation('validateTpayBlikCode')->withFailureResult($errorMessageEvent);
        }

        if (empty($this->blikCode) && $this->group < 1) {
            $errorMessageEvent = $resultFactory->createErrorMessageEvent(__('Payment method not selected'))
                ->withCustomEvent('payment:method:error');

            return $resultFactory->createValidation('validateTpayMethodSelection')->withFailureResult($errorMessageEvent);
        }

        return $resultFactory->createSuccess();
    }

    public function canUseBlikAlias()
    {
        $customerId = $this->sessionCheckout->getQuote()->getCustomerId();
        if (!$customerId) {
            return false;
        }
        try {
            $alias = $this->aliasRepository->findByCustomerId($customerId);

            return $alias->getData('cli_id') == $customerId;
        } catch (NotFoundException $exception) {
            return false;
        }
    }
}
