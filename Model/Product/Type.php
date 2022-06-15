<?php

namespace DevStone\ImageProducts\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;

/**
 * Custom Product type for Image products
 *
 * @author David Stone
 */
class Type extends \Magento\Downloadable\Model\Product\Type
{
    const TYPE_ID = 'image';

    /**
     *
     * @var \DevStone\UsageCalculator\Api\UsageRepositoryInterface
     */
    private $usageRepository;

    /**
     * Construct
     *
     * @param \Magento\Catalog\Model\Product\Option $catalogProductOption
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Catalog\Model\Product\Type $catalogProductType
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Psr\Log\LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param \Magento\Downloadable\Model\ResourceModel\SampleFactory $sampleResFactory
     * @param \Magento\Downloadable\Model\ResourceModel\Link $linkResource
     * @param \Magento\Downloadable\Model\ResourceModel\Link\CollectionFactory $linksFactory
     * @param \Magento\Downloadable\Model\ResourceModel\Sample\CollectionFactory $samplesFactory
     * @param \Magento\Downloadable\Model\SampleFactory $sampleFactory
     * @param \Magento\Downloadable\Model\LinkFactory $linkFactory
     * @param TypeHandler\TypeHandlerInterface $typeHandler
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Option $catalogProductOption,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Catalog\Model\Product\Type $catalogProductType,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Registry $coreRegistry,
        \Psr\Log\LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        \Magento\Downloadable\Model\ResourceModel\SampleFactory $sampleResFactory,
        \Magento\Downloadable\Model\ResourceModel\Link $linkResource,
        \Magento\Downloadable\Model\ResourceModel\Link\CollectionFactory $linksFactory,
        \Magento\Downloadable\Model\ResourceModel\Sample\CollectionFactory $samplesFactory,
        \Magento\Downloadable\Model\SampleFactory $sampleFactory,
        \Magento\Downloadable\Model\LinkFactory $linkFactory,
        \Magento\Downloadable\Model\Product\TypeHandler\TypeHandlerInterface $typeHandler,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {

        $this->usageRepository = $usageRepository;

        parent::__construct(
            $catalogProductOption,
            $eavConfig,
            $catalogProductType,
            $eventManager,
            $fileStorageDb,
            $filesystem,
            $coreRegistry,
            $logger,
            $productRepository,
            $sampleResFactory,
            $linkResource,
            $linksFactory,
            $samplesFactory,
            $sampleFactory,
            $linkFactory,
            $typeHandler,
            $extensionAttributesJoinProcessor,
            $serializer
        );
    }

    /**
     * Prepare additional options/information for order item which will be
     * created from this product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getOrderOptions($product)
    {
        if ($product->getCustomOption('print')) {
            $options = AbstractType::getOrderOptions($product);
            $options['print_options'] = $this->serializer->unserialize(
                $product->getCustomOption('print_options')->getValue()
            );
        } else {
            $options = parent::getOrderOptions($product);
            if ($usageId = $product->getCustomOption('usage_id')) {
                $options['usage_id'] = $usageId->getValue();
                $options['usage_options'] = $this->serializer->unserialize(
                    $product->getCustomOption('usage_options')->getValue()
                );
            }
            if ($templateOptions = $product->getCustomOption('template_options')) {
                $options['template_options'] = $this->serializer->unserialize(
                    $templateOptions->getValue()
                );
                $options['thumbnail'] = $product->getCustomOption('thumbnail');
            }
        }

        return $options;
    }

    /**
     * Delete data specific for Downloadable product type
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    public function deleteTypeSpecificData(\Magento\Catalog\Model\Product $product)
    {
        if ($product->getOrigData('type_id') === self::TYPE_ID) {
            $downloadableData = $product->getDownloadableData();
            $sampleItems = [];
            if (isset($downloadableData['sample'])) {
                foreach ($downloadableData['sample'] as $sample) {
                    $sampleItems[] = $sample['sample_id'];
                }
            }
            if ($sampleItems) {
                $this->_sampleResFactory->create()->deleteItems($sampleItems);
            }
            $linkItems = [];
            if (isset($downloadableData['link'])) {
                foreach ($downloadableData['link'] as $link) {
                    $linkItems[] = $link['link_id'];
                }
            }
            if ($linkItems) {
                $this->_linkResource->deleteItems($linkItems);
            }
        }
    }

    public function _prepareProduct(\Magento\Framework\DataObject $buyRequest, $product, $processMode)
    {

        if (($substrateSku = $buyRequest->getData('printOption'))) {
            // skip downloadable type options.
            $result = AbstractType::_prepareProduct($buyRequest, $product, $processMode);

            $this->prepareProductForPrint($buyRequest, $product);
        } elseif (($categoryId = $buyRequest->getUsageCategory())) {
            $result = parent::_prepareProduct($buyRequest, $product, $processMode);

            $product->addCustomOption('category_id', $categoryId);
            $usageIds = $buyRequest->getUsageId();
            $usageId = $usageIds[$categoryId];
            try {
                if ($buyRequest->getIncludeTemplate() === 'yes') {
                    $subProduct = clone $product;

                    $subProduct->addCustomOption(
                        'template_options',
                        $this->serializer->serialize(
                            $buyRequest->getTemplateOptions()
                        )
                    );
                    $thumbnail = $buyRequest->getThumbnail();

                    if( 'data:image/' === substr($thumbnail, 0, 11) ) {
                        $subProduct->addCustomOption('thumbnail', $buyRequest->getThumbnail());
                    }

                    $subProduct->setCartQty(1);
                    $product->setCartQty(1);
                    $product->addCustomOption( 'used_for_template', 1 );
                    array_push($result, $subProduct);
                }

                $usage = $this->usageRepository->getById($usageId);
                $product->addCustomOption('usage_id', $usageId);
                $submittedOptions = $buyRequest->getOptions();

                foreach ($usage->getOptions() as $option) {
                    if ($option->getIsRequire() && empty($submittedOptions[$option->getId()])) {
                        return __('Option "%1" is required.', $option->getTitle())->render();
                    }
                }
                $product->addCustomOption('usage_options', $this->serializer->serialize($submittedOptions));

                if ('credits' === $buyRequest->getPaymentType() && is_numeric($usage->getCredits())) {
                    $product->addCustomOption('required_credits', $usage->getCredits());
                }

            } catch (\Magento\Framework\Exception\NoSuchEntityException $exc) {
                if ($this->_isStrictProcessMode($processMode)) {
                    return __('Category or usage not found.')->render();
                }
            }
        } elseif ($this->_isStrictProcessMode($processMode)) {
            return __('Please choose a category and usage.')->render();
        }

        return $result;
    }

    public function hasRequiredOptions($product)
    {
        return true;
        return (parent::hasRequiredOptions($product) || $product->getLinksPurchasedSeparately());
    }
    /**
     * Check if downloadable product has links and they can be purchased separately
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    public function canConfigure($product)
    {
        return true;
        return $this->hasLinks($product) && $product->getLinksPurchasedSeparately();
    }

    /**
     * Check if product cannot be purchased with no links selected
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return boolean
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getLinkSelectionRequired($product)
    {
        return true;
        return $product->getLinksPurchasedSeparately();
    }

    /**
     * Prepare selected options for downloadable product
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @param  \Magento\Framework\DataObject $buyRequest
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
//    public function processBuyRequest($product, $buyRequest)
//    {
//        die('here');
//        $links = $buyRequest->getLinks();
//        var_dump($links);
//        $links = is_array($links) ? array_filter($links, 'intval') : [];
//
//        $options = ['links' => $links];
//
//        return $options;
//    }
    private function prepareProductForPrint(\Magento\Framework\DataObject $buyRequest, $product)
    {
        $product->addCustomOption('print', true);

        $options = [
            'printOption' => $buyRequest->getData('printOption'),
            'substrate' => $buyRequest->getSubstrate(),
            'imgHI' => $buyRequest->getHeight(),
            'imgWI' => $buyRequest->getWidth(),
            'canvasStyle' => $buyRequest->getData('canvasStyle'),
            'metalStyle' => $buyRequest->getData('metalStyle'),
        ];

        if ($buyRequest->getFramed() === 'yes') {
            $options['sku'] = $buyRequest->getFrame();
        }

        if ($buyRequest->getData('topMatted') === 'yes') {
            $options['mat1'] = $buyRequest->getData('topMat');
            $options['t'] =
            $options['b'] =
            $options['l'] =
            $options['r'] =
                $buyRequest->getData('topMatSize');

            if ($buyRequest->getData('bottomMatted') === 'yes') {
                $options['mat1'] = $buyRequest->getData('bottomMat');
                $options['mat2'] = $buyRequest->getData('topMat');
                $options['off'] = $buyRequest->getData('bottomMatSize');
            }
        }

        $thumbnail = $buyRequest->getThumbnail();

        if( 'data:image/' === substr($thumbnail, 0, 11) ) {
            $product->addCustomOption('thumbnail', $thumbnail);
        }

        $product->addCustomOption('print_options', $this->serializer->serialize($options));
    }

    /**
     * Check is virtual product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    public function isVirtual($product)
    {
        if ($product->getCustomOption('print')) {
            return false;
        }
        return true;
    }
    public function isSalable($product)
    {
        return $this->hasLinks($product);
    }
    public function getWeight($product)
    {
        if ($product->getCustomOption('print')) {
            $options = $this->serializer->unserialize(
                $product->getCustomOption('print_options')->getValue()
            );
            $boxes = [
                [
                    'code' => 'XL2',
                    'max_width' => 57,
                    'max_height' => 45,
                    'dimensional_weight' => 66,
                ],
                [
                    'code' => 'XL1',
                    'max_width' => 44,
                    'max_height' => 32,
                    'dimensional_weight' => 38,
                ],
                [
                    'code' => 'L1',
                    'max_width' => 38,
                    'max_height' => 25,
                    'dimensional_weight' => 28,
                ],
                [
                    'code' => 'M1',
                    'max_width' => 28,
                    'max_height' => 21,
                    'dimensional_weight' => 28,
                ],
                [
                    'code' => 'S1',
                    'max_width' => 20,
                    'max_height' => 17,
                    'dimensional_weight' => 12,
                ],
                [
                    'code' => 'XS1',
                    'max_width' => 13,
                    'max_height' => 10,
                    'dimensional_weight' => 5,
                ],
            ];

            $iw = $options['imgWI'];
            $ih = $options['imgHI'];

            if ($ih > $iw) {
                $tmp = $iw;
                $iw = $ih;
                $ih = $tmp;
            }

            foreach (array_reverse($boxes) as $box) {
                if ($iw <= $box['max_width'] && $ih <= $box['max_height']) {
                    return $box['dimensional_weight'];
                }
            }
        }
        return parent::getWeight($product); // TODO: Change the autogenerated stub
    }
}
