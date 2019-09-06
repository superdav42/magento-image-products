<?php

namespace DevStone\ImageProducts\Block\Adminhtml;

class Product extends \Magento\Catalog\Block\Adminhtml\Product
{
    
    /**
     * Retrieve product create url by specified product type
     * Override parent so we use the image attribute set for the image product type
     *
     * @param string $type
     * @return string
     */
    protected function _getProductCreateUrl($type)
    {
        if ($type === \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            $attributeSetId = 10;  // TODO get id based on name in db
        } else {
            $attributeSetId = $this->_productFactory->create()->getDefaultAttributeSetId();
        }
        
        return $this->getUrl(
            'catalog/*/new',
            ['set' => $attributeSetId, 'type' => $type]
        );
    }
}