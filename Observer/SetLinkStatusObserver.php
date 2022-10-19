<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\Collection;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\ScopeInterface;

class SetLinkStatusObserver implements ObserverInterface
{
    protected ScopeConfigInterface $scopeConfig;
    protected CollectionFactory $itemsFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $itemsFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->itemsFactory = $itemsFactory;
    }

    /**
     * Set status of link
     *
     * @param Observer $observer
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order->getId()) {
            //order not saved in the database
            return $this;
        }

        /* @var $order Order */
        $status = '';
        $linkStatuses = [
            'pending' => \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_PENDING,
            'expired' => \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_EXPIRED,
            'avail' => \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_AVAILABLE,
            'payment_pending' => \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_PENDING_PAYMENT,
            'payment_review' => \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_PAYMENT_REVIEW,
        ];

        $downloadableItemsStatuses = [];
        $orderItemStatusToEnable = $this->scopeConfig->getValue(
            \Magento\Downloadable\Model\Link\Purchased\Item::XML_PATH_ORDER_ITEM_STATUS,
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        if ($order->getState() == Order::STATE_HOLDED) {
            $status = $linkStatuses['pending'];
        } elseif ($order->isCanceled()
            || $order->getState() == Order::STATE_CLOSED
            || $order->getState() == Order::STATE_COMPLETE
        ) {
            $expiredStatuses = [
                Item::STATUS_CANCELED,
                Item::STATUS_REFUNDED,
            ];
            foreach ($order->getAllItems() as $item) {
                if ($item->getProductType() == Type::TYPE_ID
                    || $item->getRealProductType() == Type::TYPE_ID
                ) {
                    if ($order->isCanceled() || in_array($item->getStatusId(), $expiredStatuses)) {
                        $downloadableItemsStatuses[$item->getId()] = $linkStatuses['expired'];
                    } else {
                        $downloadableItemsStatuses[$item->getId()] = $linkStatuses['avail'];
                    }
                }
            }
        } elseif ($order->getState() == Order::STATE_PENDING_PAYMENT) {
            $status = $linkStatuses['payment_pending'];
        } elseif ($order->getState() == Order::STATE_PAYMENT_REVIEW) {
            $status = $linkStatuses['payment_review'];
        } else {
            $availableStatuses = [$orderItemStatusToEnable, Item::STATUS_INVOICED];
            foreach ($order->getAllItems() as $item) {
                if ($item->getProductType() == Type::TYPE_ID
                    || $item->getRealProductType() == Type::TYPE_ID
                ) {
                    if ($item->getStatusId() == Item::STATUS_BACKORDERED
                        && $orderItemStatusToEnable == Item::STATUS_PENDING
                        && !in_array(
                            Item::STATUS_BACKORDERED,
                            $availableStatuses,
                            true
                        )
                    ) {
                        $availableStatuses[] = Item::STATUS_BACKORDERED;
                    }

                    if (in_array($item->getStatusId(), $availableStatuses)) {
                        $downloadableItemsStatuses[$item->getId()] = $linkStatuses['avail'];
                    }
                }
            }
        }
        if (!$downloadableItemsStatuses && $status) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getProductType() == Type::TYPE_ID
                    || $item->getRealProductType() == Type::TYPE_ID
                ) {
                    $downloadableItemsStatuses[$item->getId()] = $status;
                }
            }
        }

        if ($downloadableItemsStatuses) {
            $linkPurchased = $this->createItemsCollection()->addFieldToFilter(
                'order_item_id',
                ['in' => array_keys($downloadableItemsStatuses)]
            );
            foreach ($linkPurchased as $link) {
                if ($link->getStatus() != $linkStatuses['expired']
                    && !empty($downloadableItemsStatuses[$link->getOrderItemId()])
                ) {
                    $link->setStatus($downloadableItemsStatuses[$link->getOrderItemId()])->save();
                }
            }
        }

        return $this;
    }

    protected function createItemsCollection(): Collection
    {
        return $this->itemsFactory->create();
    }
}
