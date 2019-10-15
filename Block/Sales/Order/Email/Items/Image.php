<?php


// @codingStandardsIgnoreFile

namespace DevStone\ImageProducts\Block\Sales\Order\Email\Items;

use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Model\Link\Purchased;
use Magento\Downloadable\Model\Link\Purchased\Item;
use Magento\Store\Model\ScopeInterface;

/**
 * Downlaodable Sales Order Email items renderer
 *
 * @api
 * @since 100.0.2
 */
class Image extends \Magento\Sales\Block\Order\Email\Items\DefaultItems
{
    /**
     * @var Purchased
     */
    protected $_purchased;

    /**
     * @var \Magento\Downloadable\Model\Link\PurchasedFactory
     */
    protected $_purchasedFactory;

    /**
     * @var \Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory
     */
    protected $_itemsFactory;

    /**
     * @var \Magento\Framework\Url
     * @since 100.1.0
     */
    protected $frontendUrlBuilder;

    /**
     * @var \DevStone\ImageProducts\Helper\Catalog\Product\Configuration
     */
    private $configuration;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Downloadable\Model\Link\PurchasedFactory $purchasedFactory
     * @param \Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory $itemsFactory
     * @param \Magento\Framework\Url $frontendUrlBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Downloadable\Model\Link\PurchasedFactory $purchasedFactory,
        \Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory $itemsFactory,
        \Magento\Framework\Url $frontendUrlBuilder,
        \DevStone\ImageProducts\Helper\Catalog\Product\Configuration $configuration,
        array $data = []
    ) {
        $this->_purchasedFactory = $purchasedFactory;
        $this->_itemsFactory = $itemsFactory;
        $this->frontendUrlBuilder = $frontendUrlBuilder;
        $this->configuration = $configuration;
        parent::__construct($context, $data);
    }

    /**
     * Enter description here...
     *
     * @return Purchased
     */
    public function getLinks()
    {
        $this->_purchased = $this->_purchasedFactory->create()->load(
            $this->getItem()->getId(),
            'order_item_id'
        );
        $purchasedLinks = $this->_itemsFactory->create()->addFieldToFilter(
            'order_item_id',
            $this->getItem()->getId()
        );
        $this->_purchased->setPurchasedItems($purchasedLinks);

        return $this->_purchased;
    }

    /**
     * @return null|string
     */
    public function getLinksTitle()
    {
        return $this->getLinks()->getLinkSectionTitle() ?: $this->_scopeConfig->getValue(
            Link::XML_PATH_LINKS_TITLE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param Item $item
     * @return string
     */
    public function getPurchasedLinkUrl($item)
    {
        return $this->frontendUrlBuilder->getUrl(
            'downloadable/download/link',
            [
                'id' => $item->getLinkHash(),
                '_scope' => $this->getOrder()->getStore(),
                '_secure' => true,
                '_nosid' => true
            ]
        );
    }
    public function getItemOptions()
    {
        $options = parent::getItemOptions();

        $productOptions = $this->getItem()->getOrderItem()->getProductOptions();

        if (!empty($productOptions['print_options'])) {
            $options = array_merge(
                $options,
                $this->configuration->getPrintOptions($productOptions['print_options'])
            );
        } elseif (!empty($productOptions['usage_id'])) {
            $options = array_merge(
                $options,
                $this->configuration->getUsageOptions($productOptions['usage_id'], $productOptions['usage_options'])
            );
        }
        return $options;
    }
}
