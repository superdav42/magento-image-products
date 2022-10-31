<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Ui\DataProvider\Product\Form\Modifier;

use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Backend\Keyword;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
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
        $this->scopeConfig = $scopeConfig;
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
            foreach (Keyword::KEYWORDS_ATTRIBUTES as $keywordAttribute) {
                // We don't need to do this with newer version of M2, Not sure why.
                if (isset($productData['product'][$keywordAttribute]) && is_string($productData['product'][$keywordAttribute])) {
                    $productData['product'][$keywordAttribute] = explode(',', $productData['product'][$keywordAttribute]);
                }
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
        foreach (Keyword::KEYWORDS_ATTRIBUTES as $keywordFieldCode) {
            $elementPath = $this->arrayManager->findPath($keywordFieldCode, $meta, null, 'children');
            $containerPath = $this->arrayManager->findPath(static::CONTAINER_PREFIX . $keywordFieldCode, $meta, null, 'children');
            $limit = $this->scopeConfig->getValue('catalog/keywords/limit_' . $keywordFieldCode);

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
                                'dataScope' => '',
                                'breakLine' => false,
                                'formElement' => 'container',
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/group',
                                'scopeLabel' => __('[GLOBAL]'),
                            ],
                        ],
                    ],
                    'children' => [
                        $keywordFieldCode => [
                            'arguments' => [
                                'data' => [
                                    'config' => [
                                        'component' => 'DevStone_ImageProducts/js/components/keywords',
                                        'componentType' => Field::NAME,
                                        'formElement' => 'select',
                                        'elementTmpl' => 'DevStone_ImageProducts/ui-select-keywords',
                                        'disableLabel' => true,
                                        'multiple' => true,
                                        'chipsEnabled' => true,
                                        'filterOptions' => false,
                                        'options' => $this->getOptions($keywordFieldCode),
                                        'maxInput' => $limit ?: 0,
                                        'filterUrl' => $this->urlBuilder->getUrl(
                                            self::SUGGEST_FILTER_URI,
                                            ['isAjax' => 'true']
                                        ),
                                    ],
                                ],
                            ],
                        ]
                    ]
                ]
            );
        }
        return $meta;
    }

    /**
     * Retrieve custom attribute collection
     *
     * @throws NoSuchEntityException
     */
    protected function getOptions($keywordAttribute): array
    {
        $product = $this->locator->getProduct();
        $attribute = $this->attributeRepository->get($keywordAttribute);
        $optionIds = explode(',', $product->getData($keywordAttribute) ?? '');
        return $attribute->getSource()->getSpecificOptions($optionIds, false);
    }
}
