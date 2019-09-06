<?php

namespace DevStone\ImageProducts\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class LoadProduct implements ObserverInterface
{


    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
//        $product->setCanShowPrice(false);
//		$product->setHasOptions(true);

    }//end execute()


}//end class
