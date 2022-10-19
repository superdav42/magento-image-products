<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class LoadProduct implements ObserverInterface
{


    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
//        $product->setCanShowPrice(false);
//		$product->setHasOptions(true);

    }//end execute()


}//end class
