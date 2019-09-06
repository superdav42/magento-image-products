<?php


namespace DevStone\ImageProducts\Ui\DataProvider\Product\Form\Modifier;


use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Form\Field;

/**
 * Data provider for "Custom Attribute" field of product page
 */
class KeywordsForm extends AbstractModifier
{
	
	/**
     * @var ArrayManager
     */
    protected $arrayManager;
    
    /**
     * @var LocatorInterface
     */
    protected $locator;
    
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
	
	/**
     * @var \Magento\Eav\Api\AttributeOptionManagementInterface
     */
    protected $attributeOptionManagement;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory
     */
    protected $optionLabelFactory;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory
     */
    protected $optionFactory;

	/**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;
	
    const SUGGEST_FILTER_URI = 'vendor_module/something/suggestCustomAttr';
	const FIELD_ORDER = 22;
    /**
     * @param LocatorInterface            $locator
     * @param UrlInterface                $urlBuilder
     * @param ArrayManager                $arrayManager
     */
    public function __construct(
        LocatorInterface $locator,
        UrlInterface $urlBuilder,
        ArrayManager $arrayManager,
		\Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
		\Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement,
        \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory
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
		foreach($data as &$productData) {
			if(isset($productData['product']['keywords']))  {
				$productData['product']['keywords'] = explode(',', $productData['product']['keywords']);
			}
		}
		
        return $data;
    }
	
	/**
     * Customise Custom Attribute field
     *
     * @param array $meta
     *
     * @return array
     */
    protected function customiseCustomAttrField(array $meta)
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
                            'label'         => __('Custom Attribute'),
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
     * @return array
     */
    protected function getOptions()
    {
		$product = $this->locator->getProduct();
		$attribute = $this->attributeRepository->get('keywords');
		$optionIds = explode(',', $product->getKeywords());
		return $attribute->getSource()->getSpecificOptions($optionIds, false);
    }
}