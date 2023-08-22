<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Product;

use DevStone\UsageCalculator\Api\UsageRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Downloadable\Model\LinkFactory;
use Magento\Downloadable\Model\Product\TypeHandler\TypeHandlerInterface;
use Magento\Downloadable\Model\ResourceModel\Link;
use Magento\Downloadable\Model\ResourceModel\Sample\CollectionFactory;
use Magento\Downloadable\Model\SampleFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;

/**
 * Custom Product type for Image products
 *
 * @author David Stone
 */
class Type extends \Magento\Downloadable\Model\Product\Type
{
    const TYPE_ID = 'image';
    private UsageRepositoryInterface $usageRepository;

    public function __construct(
        Option                                                  $catalogProductOption,
        Config                                                  $eavConfig,
        Product\Type                                            $catalogProductType,
        ManagerInterface                                        $eventManager,
        Database                                                $fileStorageDb,
        Filesystem                                              $filesystem,
        Registry                                                $coreRegistry,
        LoggerInterface                                         $logger,
        ProductRepositoryInterface                              $productRepository,
        \Magento\Downloadable\Model\ResourceModel\SampleFactory $sampleResFactory,
        Link                                                    $linkResource,
        Link\CollectionFactory                                  $linksFactory,
        CollectionFactory                                       $samplesFactory,
        SampleFactory                                           $sampleFactory,
        LinkFactory                                             $linkFactory,
        TypeHandlerInterface                                    $typeHandler,
        JoinProcessorInterface                                  $extensionAttributesJoinProcessor,
        UsageRepositoryInterface                                $usageRepository,
        Json                                                    $serializer = null
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
     * @param Product $product
     * @return array
     */
    public function getOrderOptions($product)
    {
        if ($product->getCustomOption('print')) {
            $options = AbstractType::getOrderOptions($product);
            $options['print_options'] = $this->serializer->unserialize(
                $product->getCustomOption('print_options')->getValue()
            );
            $options['thumbnail'] = $product->getCustomOption('thumbnail') ?  $product->getCustomOption('thumbnail')->getValue() : "";
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
                $options['thumbnail'] = $product->getCustomOption('thumbnail') ? $product->getCustomOption('thumbnail')->getValue() : "";
            }
        }

        $options['real_product_type'] = self::TYPE_ID;

        return $options;
    }

    /**
     * Delete data specific for Downloadable product type
     *
     * @param Product $product
     * @return void
     */
    public function deleteTypeSpecificData(Product $product)
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

    /**
     * @throws LocalizedException
     */
    public function _prepareProduct(DataObject $buyRequest, $product, $processMode)
    {
        $result = parent::_prepareProduct($buyRequest, $product, $processMode);
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

                    if ('data:image/' === substr($thumbnail, 0, 11)) {
                        $subProduct->addCustomOption('thumbnail', $buyRequest->getThumbnail());
                    }

                    $subProduct->setCartQty(1);
                    $product->setCartQty(1);
                    $product->addCustomOption('used_for_template', 1);
                    array_push($result, $subProduct);
                }

                $usage = $this->usageRepository->getById((int)$usageId);
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
            } catch (NoSuchEntityException $exc) {
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
     * @param Product $product
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
     * @param Product $product
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
     * @param  Product $product
     * @param  DataObject $buyRequest
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
    private function prepareProductForPrint(DataObject $buyRequest, $product)
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

        if ('data:image/' === substr($thumbnail, 0, 11)) {
            $product->addCustomOption('thumbnail', $thumbnail);
        }

        $product->addCustomOption('print_options', $this->serializer->serialize($options));
    }

    /**
     * Check is virtual product
     *
     * @param Product $product
     * @return bool
     */
    public function isVirtual($product): bool
    {
        if ($product->getCustomOption('print')) {
            return false;
        }
        return true;
    }

    public function isSalable($product): bool
    {
        return $this->hasLinks($product);
    }

    public function getWeight($product): ?float
    {
        if ($product->getCustomOption('print')) {
            $options = $this->serializer->unserialize(
                $product->getCustomOption('print_options')->getValue()
            );
            $substrate = $options['substrate'];
            $printOption = $options['printOption'] ?? null;
            if ('canvasPrint' === $printOption) {
                $substrate = 'canvas_'.$options['canvasStyle'];
            } elseif ('metal' === $printOption) {
                $substrate = 'metal'; // all metal have the same price.
            }

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
            $artOnlyBoxes = [
                [
                    'code' => 'TB28',
                    'max_width' => 28,
                    'max_height' => 4,
                    'max_depth' => 4,
                    'dimensional_weight' => 1.79,
                ],
                [
                    'code' => 'TB38',
                    'max_width' => 38,
                    'max_height' => 4,
                    'max_depth' => 4,
                    'dimensional_weight' => 2.43,
                ],
                [
                    'code' => 'T55',
                    'max_width' => 55,
                    'max_height' => 7,
                    'max_depth' => 7,
                    'dimensional_weight' => 10.78,
                ],
            ];

            $iw = $options['imgWI'];
            $ih = $options['imgHI'];

            if ($ih > $iw) {
                $tmp = $iw;
                $iw = $ih;
                $ih = $tmp;
            }

            if ( $substrate === 'archival' && empty($options['sku']) || $substrate === 'canvas_unstretched' ) {
                $minDimension = min($iw, $ih);
                foreach($artOnlyBoxes as $box) {
                    if ($minDimension <= $box['max_width']) {
                        return $box['dimensional_weight'];
                    }
                }
            }

            foreach (array_reverse($boxes) as $box) {
                if ($iw <= $box['max_width'] && $ih <= $box['max_height']) {
                    return $box['dimensional_weight'];
                }
            }
        }
        return parent::getWeight($product);
    }

    public function isComposite($product)
    {
        return true;
    }
}
