<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use DevStone\ImageProducts\Model\Product\Type;
use downloadable_sales_copy_link;
use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Model\Link\Purchased;
use Magento\Downloadable\Model\Link\Purchased\Item;
use Magento\Downloadable\Model\Link\Purchased\ItemFactory;
use Magento\Downloadable\Model\Link\PurchasedFactory;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\Collection;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveDownloadableOrderItemObserver implements ObserverInterface
{
    protected ScopeConfigInterface $scopeConfig;
    protected PurchasedFactory $purchasedFactory;
    protected ProductFactory $productFactory;
    protected ItemFactory $itemFactory;
    protected Copy $objectCopyService;
    protected CollectionFactory $itemsFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PurchasedFactory $purchasedFactory,
        ProductFactory $productFactory,
        ItemFactory $itemFactory,
        CollectionFactory $itemsFactory,
        Copy $objectCopyService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->purchasedFactory = $purchasedFactory;
        $this->productFactory = $productFactory;
        $this->itemFactory = $itemFactory;
        $this->itemsFactory = $itemsFactory;
        $this->objectCopyService = $objectCopyService;
    }

    /**
     * Save data from order to purchased links
     *
     * @param Observer $observer
     * @return $this
     * @throws Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute(Observer $observer)
    {
        /** @var Order\Item $orderItem */
        $orderItem = $observer->getEvent()->getItem();
        if (!$orderItem->getId()) {
            //order not saved in the database
            return $this;
        }
        $productType = $orderItem->getRealProductType() ?: $orderItem->getProductType();
        if ($productType != Type::TYPE_ID) {
            return $this;
        }
        $product = $orderItem->getProduct();
        $purchasedLink = $this->createPurchasedModel()->load($orderItem->getId(), 'order_item_id');
        if ($purchasedLink->getId()) {
            return $this;
        }
        $storeId = $orderItem->getOrder()->getStoreId();
        $orderStatusToEnableItem = $this->scopeConfig->getValue(
            Item::XML_PATH_ORDER_ITEM_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$product) {
            $product = $this->createProductModel()->setStoreId(
                $orderItem->getOrder()->getStoreId()
            )->load(
                $orderItem->getProductId()
            );
        }
        if ($product->getTypeId() == Type::TYPE_ID) {
            $links = $product->getTypeInstance()->getLinks($product);
            if ($linkIds = $orderItem->getProductOptionByCode('links')) {
                $linkPurchased = $this->createPurchasedModel();
                $this->objectCopyService->copyFieldsetToTarget(
                    'downloadable_sales_copy_order',
                    'to_downloadable',
                    $orderItem->getOrder(),
                    $linkPurchased
                );
                $this->objectCopyService->copyFieldsetToTarget(
                    'downloadable_sales_copy_order_item',
                    'to_downloadable',
                    $orderItem,
                    $linkPurchased
                );
                $linkSectionTitle = $product->getLinksTitle() ? $product
                    ->getLinksTitle() : $this
                    ->scopeConfig
                    ->getValue(
                        Link::XML_PATH_LINKS_TITLE,
                        ScopeInterface::SCOPE_STORE
                    );
                $linkPurchased->setLinkSectionTitle($linkSectionTitle)->save();
                $linkStatus = Item::LINK_STATUS_PENDING;
                if ($orderStatusToEnableItem == Order\Item::STATUS_PENDING
                    || $orderItem->getOrder()->getState() == Order::STATE_COMPLETE
                ) {
                    $linkStatus = Item::LINK_STATUS_AVAILABLE;
                }
                foreach ($linkIds as $linkId) {
                    if (isset($links[$linkId])) {
                        $linkPurchasedItem = $this->createPurchasedItemModel()->setPurchasedId(
                            $linkPurchased->getId()
                        )->setOrderItemId(
                            $orderItem->getId()
                        );

                        $this->objectCopyService->copyFieldsetToTarget(
                            downloadable_sales_copy_link::class,
                            'to_purchased',
                            $links[$linkId],
                            $linkPurchasedItem
                        );
                        $linkHash = strtr(
                            base64_encode(
                                microtime() . $linkPurchased->getId() . $orderItem->getId() . $product->getId()
                            ),
                            '+/=',
                            '-_,'
                        );
                        $numberOfDownloads = $links[$linkId]->getNumberOfDownloads() * $orderItem->getQtyOrdered();
                        $linkPurchasedItem->setLinkHash(
                            $linkHash
                        )->setNumberOfDownloadsBought(
                            $numberOfDownloads
                        )->setStatus(
                            $linkStatus
                        )->setCreatedAt(
                            $orderItem->getCreatedAt()
                        )->setUpdatedAt(
                            $orderItem->getUpdatedAt()
                        )->save();
                    }
                }
            }
        }

        return $this;
    }

    protected function createPurchasedModel(): Purchased
    {
        return $this->purchasedFactory->create();
    }

    protected function createProductModel(): Product
    {
        return $this->productFactory->create();
    }

    protected function createPurchasedItemModel(): Item
    {
        return $this->itemFactory->create();
    }

    protected function createItemsCollection(): Collection
    {
        return $this->itemsFactory->create();
    }
}
