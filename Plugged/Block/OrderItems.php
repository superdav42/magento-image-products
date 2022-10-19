<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Plugged\Block;

use Magento\Sales\Block\Adminhtml\Order\View\Items;

class OrderItems
{
    public function afterGetColumns(Items $block, $columns)
    {
        $column = array_pop($columns);
        array_unshift($columns, $column);
        return $columns;
    }
}
