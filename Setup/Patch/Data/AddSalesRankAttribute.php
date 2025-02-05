<?php

namespace DevStone\ImageProducts\Setup\Patch\Data;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Setup\EavSetup;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddSalesRankAttribute implements DataPatchInterface
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
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'sales_rank',
            [
                'type' => 'decimal',
                'backend' => '',
                'frontend' => '',
                'label' => 'Sales Rank',
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
                'visible_on_front' => false,
                'unique' => false,
                'apply_to' => Type::TYPE_ID,
                'used_in_product_listing' => false,
                'is_html_allowed_on_front' => false,
                'is_wysiwyg_enabled' => true,
                'is_used_for_promo_rules' => true, // doesn't work. Need to update catalog_product_eav manually.
            ]
        );

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }
}
