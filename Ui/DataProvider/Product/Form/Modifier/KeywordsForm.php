<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Form\Field;

/**
 * Data provider for "Custom Attribute" field of product page
 */
class KeywordsForm extends AbstractModifier
{

    protected ArrayManager $arrayManager;
    protected LocatorInterface $locator;
    protected UrlInterface $urlBuilder;
    protected AttributeOptionManagementInterface $attributeOptionManagement;
    protected AttributeOptionLabelInterfaceFactory $optionLabelFactory;
    protected AttributeOptionInterfaceFactory $optionFactory;
    protected ProductAttributeRepositoryInterface $attributeRepository;

    const SUGGEST_FILTER_URI = 'vendor_module/something/suggestCustomAttr';
    const FIELD_ORDER = 22;

    public function __construct(
        LocatorInterface $locator,
        UrlInterface $urlBuilder,
        ArrayManager $arrayManager,
        ProductAttributeRepositoryInterface $attributeRepository,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        AttributeOptionInterfaceFactory $optionFactory
    ) {
        $this->locator = $locator;
        $this->urlBuilder = $urlBuilder;
        $this->arrayManager = $arrayManager;
        $this->attributeRepository = $attributeRepository;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->optionFactory = $optionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $meta = $this->customiseCustomAttrField($meta);

        return $meta;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyData(array $data)
    {
        foreach ($data as &$productData) {
            // We don't need to do this with newer version of M2, Not sure why.
            if (isset($productData['product']['keywords']) && is_string($productData['product']['keywords'])) {
                $productData['product']['keywords'] = explode(',', $productData['product']['keywords']);
            }
        }
        return $data;
    }

    /**
     * Customise Custom Attribute field
     *
     * @throws NoSuchEntityException
     */
    protected function customiseCustomAttrField(array $meta): array
    {
        $fieldCode = 'keywords'; //your custom attribute code
        $elementPath = $this->arrayManager->findPath($fieldCode, $meta, null, 'children');
        $containerPath = $this->arrayManager->findPath(static::CONTAINER_PREFIX . $fieldCode, $meta, null, 'children');

        if (!$elementPath) {
            return $meta;
        }

        $meta = $this->arrayManager->merge(
            $containerPath,
            $meta,
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'dataScope'     => '',
                            'breakLine'     => false,
                            'formElement'   => 'container',
                            'componentType' => 'container',
                            'component'     => 'Magento_Ui/js/form/components/group',
                            'scopeLabel'    => __('[GLOBAL]'),
                        ],
                    ],
                ],
                'children'  => [
                    $fieldCode => [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'component'     => 'DevStone_ImageProducts/js/components/keywords',
                                    'componentType' => Field::NAME,
                                    'formElement'   => 'select',
                                    'elementTmpl'   => 'DevStone_ImageProducts/ui-select-keywords',
                                    'disableLabel'  => true,
                                    'multiple'      => true,
                                    'chipsEnabled'  => true,
                                    'filterOptions' => false,
                                    'options'       => $this->getOptions(),
                                    'filterUrl'     => $this->urlBuilder->getUrl(
                                        self::SUGGEST_FILTER_URI,
                                        ['isAjax' => 'true']
                                    ),
//                                    'config'           => [
//                                        'dataScope' => $fieldCode,
//                                        'sortOrder' => self::FIELD_ORDER,
//                                    ],
                                ],
                            ],
                        ],
                    ]
                ]
            ]
        );

        return $meta;
    }

    /**
     * Retrieve custom attribute collection
     *
     * @throws NoSuchEntityException
     */
    protected function getOptions(): array
    {
        $product = $this->locator->getProduct();
        $attribute = $this->attributeRepository->get('keywords');
        $optionIds = explode(',', $product->getKeywords() ?? '');
        return $attribute->getSource()->getSpecificOptions($optionIds, false);
    }
}
