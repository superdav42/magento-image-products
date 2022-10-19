<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Setup\Patch\Data;

use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Backend\Keyword as KeywordBackend;
use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend\Keyword as KeywordFrontend;
use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source\Keyword as KeywordSource;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddAdditionalKeywords implements DataPatchInterface
{
    protected ModuleDataSetupInterface $moduleDataSetup;
    protected EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'secondary_keywords',
            [
                'type' => 'varchar',
                'backend' => KeywordBackend::class,
                'frontend' => KeywordFrontend::class,
                'label' => 'Secondary Keywords',
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
                'search_weight' => 7
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'tertiary_keywords',
            [
                'type' => 'varchar',
                'backend' => KeywordBackend::class,
                'frontend' => KeywordFrontend::class,
                'label' => 'Tertiary Keywords',
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
                'search_weight' => 3
            ]
        );

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}
