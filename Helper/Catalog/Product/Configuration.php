<?php


// @codingStandardsIgnoreFile

namespace DevStone\ImageProducts\Helper\Catalog\Product;

/**
 * Helper for fetching properties by product configurational item
 *
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Configuration extends \Magento\Framework\App\Helper\AbstractHelper implements
    \Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface
{
    /**
     * Catalog product configuration
     *
     * @var \Magento\Catalog\Helper\Product\Configuration
     */
    protected $productConfig = null;

    /**
     *
     * @var \DevStone\UsageCalculator\Api\UsageRepositoryInterface
     */
    protected $usageRepository;

    /**
     *
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Catalog\Helper\Product\Configuration $productConfig
     * @param \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Helper\Product\Configuration $productConfig,
        \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer
    )
    {
        $this->productConfig = $productConfig;
        $this->usageRepository = $usageRepository;
        $this->serializer = $serializer;
        parent::__construct($context);
    }

    /**
     * Retrieves item links options
     *
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @return array
     */
    public function getLinks(\Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item)
    {
        $product = $item->getProduct();
        $itemLinks = [];
        $linkIds = $item->getOptionByCode('downloadable_link_ids');
        if ($linkIds) {
            $productLinks = $product->getTypeInstance()->getLinks($product);
            foreach (explode(',', $linkIds->getValue()) as $linkId) {
                if (isset($productLinks[$linkId])) {
                    $itemLinks[] = $productLinks[$linkId];
                }
            }
        }
        return $itemLinks;
    }

    /**
     * Retrieves product links section title
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getLinksTitle($product)
    {
        $title = $product->getLinksTitle();
        if (strlen($title)) {
            return $title;
        }
        return $this->scopeConfig->getValue(\Magento\Downloadable\Model\Link::XML_PATH_LINKS_TITLE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param \Magento\Catalog\Block\Product\Image $image
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     */
    public function updateImage(
        \Magento\Catalog\Block\Product\Image $image,
        \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
    ) {
        if ($dataUrl = $item->getOptionByCode('thumbnail')) {
            $image->setImageUrl($dataUrl->getValue());
        }
    }

    /**
     * Retrieves product options
     *
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @return array
     */
    public function getOptions(\Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item)
    {
        $options = $this->productConfig->getOptions($item);

        if ($item->getOptionByCode('print_options')) {
            $newOptions = $this->getPrintOptions(
                $this->serializer->unserialize(
                    $item->getOptionByCode('print_options')->getValue()
                )
            );
        } elseif(($usageOption = $item->getOptionByCode('usage_id'))) {
            $newOptions = $this->getUsageOptions(
                $usageOption->getValue(),
                $this->serializer->unserialize(
                    $item->getOptionByCode('usage_options')->getValue()
                )
            );
        } else {
            $newOptions = [];
        }

        $options = array_merge($options, $newOptions);

        return $options;
    }

    public function getUsageOptions($usageId, $usageOptions)
    {
        $terms = '';
        try {
            $usage = $this->usageRepository->getById($usageId);

            $terms = $usage->getTerms();

            foreach($usage->getOptions() as $option) {
                if ( !empty($usageOptions[$option->getId()])) {
                    if ( is_numeric($usageOptions[$option->getId()])) {
                        $valueObject = $option->getValueById($usageOptions[$option->getId()]);
                        if (!$valueObject) {
                            $value = $usageOptions[$option->getId()];
                        } else {
                            $value = $valueObject->getTitle();
                        }
                    } else {
                        $value = $usageOptions[$option->getId()];
                    }
                    $terms = str_replace('('.$option->getTitle().')', '<strong>'.$value.'</strong>', $terms);
                }
            }

        } catch (\Magento\Framework\Exception\LocalizedException $exc) {
        }

        return [['label' => __('Terms'), 'value' => $terms, 'custom_view' => true]];
    }

    public function getPrintOptions($printOptions)
    {

        $options = [
            ['label' => __('Substrate'), 'value' => $printOptions['printOption']],
            ['label' => __('Width'), 'value' => $printOptions['imgWI'].' '.__('Inches')],
            ['label' => __('Height'), 'value' => $printOptions['imgHI'].' '.__('Inches')],
        ];

        if (!empty($printOptions['sku'])) {
            $options[] = ['label' => __('Frame'), 'value' => $printOptions['sku']];
        }

        if (!empty($printOptions['mat2'])) {
            $options[] = ['label' => __('Top Mat'), 'value' => $printOptions['mat2']];
            $options[] = ['label' => __('Top Mat Size'), 'value' => $printOptions['t'].' '.__('Inches')];
            $options[] = ['label' => __('Bottom Mat'), 'value' => $printOptions['mat1']];
            $options[] = ['label' => __('Bottom Mat Size'), 'value' => $printOptions['off'].' '.__('Inches')];
        } elseif (!empty($printOptions['mat1'])) {
            $options[] = ['label' => __('Top Mat'), 'value' => $printOptions['mat1']];
            $options[] = ['label' => __('Top Mat Size'), 'value' => $printOptions['t'].' '.__('Inches')];
        }

        return $options;
    }
}
