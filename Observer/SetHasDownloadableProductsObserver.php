<?php
namespace DevStone\ImageProducts\Observer;


use Magento\Framework\Event\ObserverInterface;

class SetHasDownloadableProductsObserver implements ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Set checkout session flag if order has downloadable product(s)
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->_checkoutSession->getHasDownloadableProducts()) {
            $order = $observer->getEvent()->getOrder();
            foreach ($order->getAllItems() as $item) {
                /* @var $item \Magento\Sales\Model\Order\Item */
                if (($item->getProductType() == \DevStone\ImageProducts\Model\Product\Type::TYPE_ID
                    || $item->getRealProductType() == \DevStone\ImageProducts\Model\Product\Type::TYPE_ID
                    || $item->getProductOptionByCode(
                        'is_downloadable'
                    )) && !$item->getProductOptionByCode('print')
                ) {
                    $this->_checkoutSession->setHasDownloadableProducts(true);
                    break;
                }
            }
        }

        return $this;
    }
}
