<?php

namespace DevStone\ImageProducts\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use DevStone\ImageProducts\Model\Product\Type;

/**
 * Upgrade Data script
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UpgradeData implements UpgradeDataInterface
{

    /**
     * EAV setup factory
     *
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Constructor
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($context->getVersion()
            && version_compare($context->getVersion(), '1.0.3') < 0
        ) {
            /** @var EavSetup $eavSetup */
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            /**
             * Add attributes to the eav/attribute table
             */

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'scriptures',
                [
                    'type' => 'varchar',
                    'backend' => \DevStone\ImageProducts\Model\Eav\Entity\Attribute\Backend\Scripture::class,
                    'frontend' => \DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend\Scripture::class,
                    'label' => 'Scriptures',
                    'input' => 'multiselect',
                    'class' => '',
                    'source' => \DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source\Scripture::class,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
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
        }

        $setup->endSetup();
    }
}
