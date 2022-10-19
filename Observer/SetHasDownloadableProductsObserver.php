<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Item;

class SetHasDownloadableProductsObserver implements ObserverInterface
{
    protected Session $checkoutSession;

    public function __construct(
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Set checkout session flag if order has downloadable product(s)
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        if (!$this->checkoutSession->getHasDownloadableProducts()) {
            $order = $observer->getEvent()->getOrder();
            foreach ($order->getAllItems() as $item) {
                /* @var $item Item */
                if (($item->getProductType() == Type::TYPE_ID
                    || $item->getRealProductType() == Type::TYPE_ID
                    || $item->getProductOptionByCode(
                        'is_downloadable'
                    )) && !$item->getProductOptionByCode('print')
                ) {
                    $this->checkoutSession->setHasDownloadableProducts(true);
                    break;
                }
            }
        }

        return $this;
    }
}
