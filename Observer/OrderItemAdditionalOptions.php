<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class OrderItemAdditionalOptions implements ObserverInterface
{
    protected LayoutInterface $layout;
    protected StoreManagerInterface $storeManager;
    protected RequestInterface $request;
    protected LoggerInterface $logger;
    protected Json $serializer;

    public function __construct(
        StoreManagerInterface $storeManager,
        LayoutInterface $layout,
        RequestInterface $request,
        LoggerInterface $logger,
        Json $serializer
    ) {
        $this->layout = $layout;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }
    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // This code failed. Leaving because we may want to get it working later.
        return;

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
