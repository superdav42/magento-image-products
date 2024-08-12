<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 6/25/18
 * Time: 2:29 PM
 */

namespace DevStone\ImageProducts\Block\Sales\Order\Item\Renderer;

use Magento\Downloadable\Block\Sales\Order\Item\Renderer\Downloadable;
use Magento\Downloadable\Model\Link\Purchased\Item;

class Image extends Downloadable
{
    /**
     * @var \DevStone\ImageProducts\Helper\Catalog\Product\Configuration
     */
    private $configuration;

	/**
	 * @param \Magento\Framework\View\Element\Template\Context $context
	 * @param \Magento\Framework\Stdlib\StringUtils $string
	 * @param \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory
	 * @param \Magento\Downloadable\Model\Link\PurchasedFactory $purchasedFactory
	 * @param \Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory $itemsFactory
	 * @param array $data
	 * @param \Magento\Downloadable\Model\Sales\Order\Link\Purchased|null $purchasedLink
	 */
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Framework\Stdlib\StringUtils $string,
		\Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
		\Magento\Downloadable\Model\Link\PurchasedFactory $purchasedFactory,
		\Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory $itemsFactory,
		\Magento\Downloadable\Model\Sales\Order\Link\Purchased $purchasedLink,
		\DevStone\ImageProducts\Helper\Catalog\Product\Configuration $configuration,
		private readonly \Magento\Catalog\Block\Product\ImageBuilder $imageBuilder,
		private readonly \Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface $itemResolver,
		array $data = [],
	) {
		parent::__construct($context, $string, $productOptionFactory, $purchasedFactory, $itemsFactory, $data, $purchasedLink);
        $this->configuration = $configuration;
    }

    public function getItemOptions()
    {
        $options = parent::getItemOptions();

        $productOptions = $this->getOrderItem()->getProductOptions('print_options');
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

	/**
	 * Return url to download link
	 *
	 * @param Item $item
	 * @return string
	 */
	public function getDownloadUrl($item)
	{
		return $this->getUrl('downloadable/download/link', ['id' => $item->getLinkHash(), '_secure' => true]);
	}

    /**
     * Return true if target of link new window
     *
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsOpenInNewWindow()
    {
        return $this->_scopeConfig->isSetFlag(
            \Magento\Downloadable\Model\Link::XML_PATH_TARGET_NEW_WINDOW,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }


	/**
	 * Retrieve product image
	 *
	 * @param \Magento\Catalog\Model\Product $product
	 * @param string $imageId
	 * @param array $attributes
	 * @return \Magento\Catalog\Block\Product\Image
	 */
	public function getImage($product, $imageId, $attributes = [])
	{
		return $this->imageBuilder->create($product, $imageId, $attributes);
	}


	/**
	 * Identify the product from which thumbnail should be taken.
	 *
	 * @return \Magento\Catalog\Model\Product
	 * @codeCoverageIgnore
	 */
	public function getProductForThumbnail()
	{
		$item = $this->getItem();
		/** @var \Magento\Sales\Model\Order\Item $item */
		$item->getProduct();
		return $this->itemResolver->getFinalProduct($this->getItem());
	}
}