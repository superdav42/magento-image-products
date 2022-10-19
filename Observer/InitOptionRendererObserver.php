<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use Magento\Downloadable\Helper\Catalog\Product\Configuration;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class InitOptionRendererObserver implements ObserverInterface
{
    /**
     * Initialize product options renderer with downloadable specific params
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $block = $observer->getBlock();
        $block->addOptionsRenderCfg('downloadable', Configuration::class);

        return $this;
    }
}
