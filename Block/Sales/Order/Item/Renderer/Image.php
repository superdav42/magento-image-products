<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 6/25/18
 * Time: 2:29 PM
 */

namespace DevStone\ImageProducts\Block\Sales\Order\Item\Renderer;

use Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer;

class Image extends DefaultRenderer
{
    /**
     * @var \DevStone\ImageProducts\Helper\Catalog\Product\Configuration
     */
    private $configuration;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \DevStone\ImageProducts\Helper\Catalog\Product\Configuration $configuration,
        array $data = []
    ) {
        $this->configuration = $configuration;
        parent::__construct($context, $string, $productOptionFactory, $data);
    }

    public function getItemOptions()
    {
        $options = parent::getItemOptions();

        $productOptions = $this->getOrderItem()->getProductOptions('print_options');
        if (!empty($productOptions['print_options'])) {
            $options = array_merge(
                $options,
                $this->configuration->getPrintOptions($productOptions['print_options'])
            );
        } elseif (!empty($productOptions['usage_id'])) {
            $options = array_merge(
                $options,
                $this->configuration->getUsageOptions($productOptions['usage_id'], $productOptions['usage_options'])
            );
        }
        return $options;
    }
}