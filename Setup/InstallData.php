<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Setup;

use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Backend\Keyword as KeywordBackend;
use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend\Keyword as KeywordFrontend;
use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source\Keyword as KeywordSource;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;

use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    private EavSetupFactory $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        /**
         * Add attributes to the eav/attribute table
         */

        $eavSetup->addAttribute(
            Product::ENTITY,
            'keywords',
            [
                'type' => 'varchar',
                'backend' => KeywordBackend::class,
                'frontend' => KeywordFrontend::class,
                'label' => 'Keywords',
                'input' => 'multiselect',
                'class' => '',
                'source' => KeywordSource::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => true,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false,
                'is_html_allowed_on_front' => true,
                'is_wysiwyg_enabled' => true,
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'width',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Width',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => false,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'height',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Height',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => false,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'image_type',
            [
                'type' => 'varchar',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'frontend' => '',
                'label' => 'Type',
                'input' => 'select',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => true,
                'user_defined' => true,
                'default' => '',
                'searchable' => true,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false,
                'option' => [
                    'values' => [
                        'as' => 'Illustrations',
                        'ps' => 'Photography',
                    ]
                ],
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'orientation',
            [
                'type' => 'int',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'frontend' => '',
                'label' => 'Orientation',
                'input' => 'select',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => false,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => true,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false,
                'option' => [
                    'values' => [
                        'vertical' => 'Vertical',
                        'horizontal' => 'Horizontal',
                        'square' => 'Square',
                        'panoramic_vertical' => 'Panoramic Vertical',
                        'panoramic_horizontal' => 'Panoramic Horizontal',
                    ]
                ],
            ]
        );

        $fieldList = [
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'minimal_price',
            'cost',
            'tier_price',
            'weight',
            'links_exist',
        ];

        // make these attributes applicable to image products
        foreach ($fieldList as $field) {
            $applyTo = explode(
                ',',
                $eavSetup->getAttribute(Product::ENTITY, $field, 'apply_to')
            );
            if (!in_array(Type::TYPE_ID, $applyTo)) {
                $applyTo[] = Type::TYPE_ID;
                $eavSetup->updateAttribute(
                    Product::ENTITY,
                    $field,
                    'apply_to',
                    implode(',', $applyTo)
                );
            }
        }
    }
}
