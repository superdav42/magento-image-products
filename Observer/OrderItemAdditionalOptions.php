<?php

namespace DevStone\ImageProducts\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;

class OrderItemAdditionalOptions implements ObserverInterface
{

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Framework\App\RequestInterface $request,
        \Psr\Log\LoggerInterface $logger,
        Json $serializer = null
    )
    {
        $this->_layout = $layout;
        $this->_storeManager = $storeManager;
        $this->_request = $request;
        $this->logger = $logger;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
    }
    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $quote = $observer->getQuote();
        $order = $observer->getOrder();
        $quoteItems = [];

        // Map Quote Item with Quote Item Id
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $quoteItems[$quoteItem->getId()] = $quoteItem;
        }

        foreach ($order->getAllVisibleItems() as $orderItem) {
            $quoteItemId = $orderItem->getQuoteItemId();
            $quoteItem = $quoteItems[$quoteItemId];
            $additionalOptions = $quoteItem->getOptionByCode('print_options');


            if ($additionalOptions) {
                // Get Order Item's other options
                $options = $orderItem->getProductOptions();
                // Set additional options to Order Item
                // Causes admin to break. Not sure what the problem is
                // We want to be able to see product options in admin and this was supposed to fix it but it don't
//                $options['options'] = $this->serializer->unserialize($additionalOptions->getValue());
//                $orderItem->setProductOptions($options);
            }
        }

    }
}
