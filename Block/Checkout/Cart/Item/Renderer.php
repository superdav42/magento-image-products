<?php


/**
 * Shopping cart downloadable item render block
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
namespace DevStone\ImageProducts\Block\Checkout\Cart\Item;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;

/**
 * @api
 * @since 100.0.2
 */
class Renderer extends \Magento\Checkout\Block\Cart\Item\Renderer
{
    /**
     * Downloadable catalog product configuration
     *
     * @var \Magento\Downloadable\Helper\Catalog\Product\Configuration
     */
    protected $imageProductConfiguration = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Catalog\Helper\Product\Configuration $productConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Catalog\Block\Product\ImageBuilder $imageBuilder
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param InterpretationStrategyInterface $messageInterpretationStrategy
     * @param \Magento\Downloadable\Helper\Catalog\Product\Configuration $imageProductConfiguration
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Helper\Product\Configuration $productConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Block\Product\ImageBuilder $imageBuilder,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\Module\Manager $moduleManager,
        InterpretationStrategyInterface $messageInterpretationStrategy,
		\DevStone\ImageProducts\Helper\Catalog\Product\Configuration $imageProductConfiguration,
        array $data = []
    ) {
        $this->imageProductConfiguration = $imageProductConfiguration;
        parent::__construct(
            $context,
            $productConfig,
            $checkoutSession,
            $imageBuilder,
            $urlHelper,
            $messageManager,
            $priceCurrency,
            $moduleManager,
            $messageInterpretationStrategy,
            $data
        );
    }

    /**
     * Retrieves item links options
     *
     * @return array
     */
    public function getLinks()
    {
        if (!$this->getItem()) {
            return [];
        }
        return $this->imageProductConfiguration->getLinks($this->getItem());
    }

    /**
     * Return title of links section
     *
     * @return string
     */
    public function getLinksTitle()
    {
        return $this->imageProductConfiguration->getLinksTitle($this->getProduct());
    }

    /**
     * Get list of all options for product
     *
     * @return array
     */
    public function getOptionList()
    {
        return $this->imageProductConfiguration->getOptions($this->getItem());
    }

    /**
     * Get list of all options for product
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @return array
     */
    public function getOption($item)
    {
        return $this->imageProductConfiguration->getOptions($item);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $imageId
     * @param array $attributes
     * @return \Magento\Catalog\Block\Product\Image
     */
    public function getImage($product, $imageId, $attributes = [])
    {
        if( !$product) {

        }
        $image = parent::getImage($product, $imageId, $attributes);

        $this->imageProductConfiguration->updateImage($image, $this->getItem());

        return $image;
    }

    public function getProductName()
    {
        $name = parent::getProductName();
        if (!empty($this->getItem()->getOptionByCode('template_options'))) {
            return $name . ' - ' . __('Custom Template');
        };
        return $name;
    }
}
