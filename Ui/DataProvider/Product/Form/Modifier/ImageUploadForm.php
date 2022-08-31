<?php

namespace DevStone\ImageProducts\Ui\DataProvider\Product\Form\Modifier;


use DevStone\ImageProducts\Model\Product\Type as ProductType;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Downloadable\Model\Link;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Form;


use Magento\Framework\Stdlib\ArrayManager;

use Magento\Downloadable\Ui\DataProvider\Product\Form\Modifier\Composite;
use Magento\Ui\Component\Container;
use Magento\Ui\Component\DynamicRows;


/**
 * Customize Downloadable panel
 */
class ImageUploadForm extends AbstractModifier
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
     * @var Data\Links
     */
    protected $linksData;

    /**
     * @var string
     */
    protected $uploadPath;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @param LocatorInterface $locator
     * @param UrlInterface $urlBuilder
     * @param ArrayManager $arrayManager
     * @param Data\Links $linksData
     * @param \Magento\Framework\App\State $state
     * @param string $uploadPath
     */
    public function __construct(
        LocatorInterface $locator,
        UrlInterface $urlBuilder,
        ArrayManager $arrayManager,
        Data\Links $linksData,
        \Magento\Framework\App\State $state,
        $uploadPath = 'adminhtml/downloadable_file/upload'
    ) {
        $this->locator = $locator;
        $this->urlBuilder = $urlBuilder;
        $this->arrayManager = $arrayManager;
        $this->linksData = $linksData;
        $this->uploadPath = $uploadPath;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyData(array $data)
    {
        $product = $this->locator->getProduct();
        if ($product->getTypeId() === ProductType::TYPE_ID) {
            $data[$product->getId()]['downloadable']['link'] = $this->linksData->getLinksData();

            return $data;

        }

        return $data;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function modifyMeta(array $meta)
    {

        $panelConfig['arguments']['data']['config'] = [
            'componentType' => Form\Fieldset::NAME,
            'label' => __('Image Information'),
            'collapsible' => true,
            'opened' => true,
            'sortOrder' => 20,
            'dataScope' => 'data'
        ];
        $meta = $this->arrayManager->set('downloadable', $meta, $panelConfig);

        $linksPath = Composite::CHILDREN_PATH . '/' . Composite::CONTAINER_LINKS;

        $linksContainer['arguments']['data']['config'] = [
            'componentType' => Form\Fieldset::NAME,
            'additionalClasses' => 'admin__fieldset-section',
            'label' => null,
            'dataScope' => '',
            'visible' => true,
            'sortOrder' => 20,
        ];

        $linksContainer = $this->arrayManager->set(
            'children',
            $linksContainer,
            [
                'link' => $this->getDynamicRows(),
            ]
        );

        return $this->arrayManager->set($linksPath, $meta, $linksContainer);
    }

    /**
     * @return array
     */
    protected function getDynamicRows()
    {
        $dynamicRows['arguments']['data']['config'] = [
            'addButtonLabel' => __('Add New Image'),
            'componentType' => DynamicRows::NAME,
            'itemTemplate' => 'record',
            'renderDefaultRecord' => false,
            'columnsHeader' => true,
            'additionalClasses' => 'admin__field-no-label',
            'dataScope' => 'downloadable',
            'deleteProperty' => 'is_delete',
            'deleteValue' => '1',
        ];

        return $this->arrayManager->set('children/record', $dynamicRows, $this->getRecord());
    }

    /**
     * @return array
     */
    protected function getRecord()
    {
        $record['arguments']['data']['config'] = [
            'componentType' => Container::NAME,
            'isTemplate' => true,
            'is_collection' => true,
            'component' => 'Magento_Ui/js/dynamic-rows/record',
            'dataScope' => '',
        ];
        $recordPosition['arguments']['data']['config'] = [
             'componentType' => Form\Field::NAME,
            'formElement' => Form\Element\Input::NAME,
            'dataType' => Form\Element\DataType\Number::NAME,
            'dataScope' => 'sort_order',
            'visible' => false,
        ];
        $recordActionDelete['arguments']['data']['config'] = [
            'label' => null,
            'componentType' => 'actionDelete',
            'fit' => true,
        ];

        if ($this->state->getAreaCode() === 'adminhtml') {
            $childern = [
                'container_file' => $this->getFileColumn(),
                'max_downloads' => $this->getMaxDownloadsColumn(),
                'gallery_size' => $this->getGallerySizeColumn(),
            ];
        } else {
            $childern = [
                'container_file' => $this->getFileColumn(),
                'max_downloads' => $this->getMaxDownloadsColumn(),
                'gallery_size' => $this->linkTypeColumn(),
            ];
        }
        return $this->arrayManager->set(
            'children',
            $record,
            $childern,
        );
    }

    /**
     * @return array
     */
    protected function getFileColumn()
    {
        $fileContainer['arguments']['data']['config'] = [
            'componentType' => Container::NAME,
            'formElement' => Container::NAME,
            'component' => 'Magento_Ui/js/form/components/group',
            'label' => null,
            'dataScope' => '',
        ];
        $fileTypeField['arguments']['data']['config'] = [
            'formElement' => Form\Element\Hidden::NAME,
            'componentType' => Form\Field::NAME,
            'additionalClasses' => 'hidden',
            'dataType' => Form\Element\DataType\Text::NAME,
            'dataScope' => 'type',
            'value' => 'file',
        ];

        $fileUploader['arguments']['data']['config'] = [
            'formElement' => 'fileUploader',
            'componentType' => 'fileUploader',
            'component' => 'Magento_Downloadable/js/components/file-uploader',
            'elementTmpl' => 'Magento_Downloadable/components/file-uploader',
            'fileInputName' => 'links',
            'uploaderConfig' => [
                'url' => $this->urlBuilder->getUrl(
                    $this->uploadPath,
                    ['type' => 'links', '_secure' => true]
                ),
            ],
            'dataScope' => 'file',
            'validation' => [
                'required-entry' => true,
            ],
        ];

        return $this->arrayManager->set(
            'children',
            $fileContainer,
            [
//                'type' => $fileTypeField,
                'links_file' => $fileUploader
            ]
        );
    }

    /**
     * @return array
     */
    protected function getMaxDownloadsColumn()
    {

        $numberOfDownloadsField['arguments']['data']['config'] = [
            'formElement' => Form\Element\Hidden::NAME,
            'componentType' => Form\Field::NAME,
            'dataType' => Form\Element\DataType\Number::NAME,
            'dataScope' => 'number_of_downloads',
            'value' => 0,
            'validation' => [
                'validate-zero-or-greater' => true,
                'validate-number' => true,
            ],
        ];
        return $numberOfDownloadsField;

    }


    /**
     * Returns Gallery Size columns configuration
     *
     * @return array
     */
    protected function getGallerySizeColumn()
    {
        $shareableField['arguments']['data']['config'] = [
            'label' => __('Gallery Size'),
            'formElement' => Form\Element\Select::NAME,
            'componentType' => Form\Field::NAME,
            'dataType' => Form\Element\DataType\Text::NAME,
            'dataScope' => 'type',
            'sortOrder' => 50,
            'options' => [
                ['value' => 'file', 'label' => __('Default Image')],
                ['value' => 'gal_10', 'label' => __('Gallery Size of 10"')],
                ['value' => 'gal_12', 'label' => __('Gallery Size of 12"')],
                ['value' => 'gal_14', 'label' => __('Gallery Size of 14"')],
                ['value' => 'gal_16', 'label' => __('Gallery Size of 16"')],
                ['value' => 'gal_20', 'label' => __('Gallery Size of 20"')],
                ['value' => 'gal_24', 'label' => __('Gallery Size of 24"')],
                ['value' => 'gal_30', 'label' => __('Gallery Size of 30"')],
                ['value' => 'gal_36', 'label' => __('Gallery Size of 36"')],
                ['value' => 'gal_40', 'label' => __('Gallery Size of 40"')],
            ],
        ];

        return $shareableField;
    }

    /**
     * @return array
     */
    protected function linkTypeColumn()
    {
        $linkTypeField['arguments']['data']['config'] = [
            'formElement' => Form\Element\Hidden::NAME,
            'componentType' => Form\Field::NAME,
            'dataType' => Form\Element\DataType\Text::NAME,
            'dataScope' => 'type',
            'value' => 'file',
        ];

        return $linkTypeField;
    }
}
