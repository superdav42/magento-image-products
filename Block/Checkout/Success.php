<?php

namespace DevStone\ImageProducts\Block\Checkout;

class Success extends \Magento\Downloadable\Block\Checkout\Success
{

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer,
        private readonly \Magento\Downloadable\Model\Sales\Order\Link\Purchased $purchasedLink,
        array $data = []
    )
    {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $currentCustomer);
    }
    public function getDownloadableLinks()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $items = $order->getAllItems();
        $downloadableLinks = [];


        foreach ($items as $item) {
            $downloadableLinks[] = $this->purchasedLink->getLink($item);
        }
        return $downloadableLinks;
    }
}
