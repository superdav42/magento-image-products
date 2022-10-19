<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class IsAllowedGuestCheckoutObserver implements ObserverInterface
{
    /**
     *  Xml path to disable checkout
     */
    const XML_PATH_DISABLE_GUEST_CHECKOUT = 'catalog/downloadable/disable_guest_checkout';

    protected ScopeConfigInterface $_scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Check is allowed guest checkout if quote contain downloadable product(s)
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $store = $observer->getEvent()->getStore();
        $result = $observer->getEvent()->getResult();

        if (!$this->_scopeConfig->isSetFlag(
            self::XML_PATH_DISABLE_GUEST_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        )) {
            return $this;
        }

        /* @var $quote Quote */
        $quote = $observer->getEvent()->getQuote();

        foreach ($quote->getAllItems() as $item) {
            if (($product = $item->getProduct())
                && $product->getTypeId() == Type::TYPE_ID
            ) {
                $result->setIsAllowed(false);
                break;
            }
        }

        return $this;
    }
}
