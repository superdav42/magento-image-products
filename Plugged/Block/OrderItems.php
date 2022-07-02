<?php

namespace DevStone\ImageProducts\Plugged\Block;

class OrderItems
{
    public function afterGetColumns(\Magento\Sales\Block\Adminhtml\Order\View\Items $block, $columns) {
        $column = array_pop($columns);
        array_unshift($columns, $column);
        return $columns;
    }
}
