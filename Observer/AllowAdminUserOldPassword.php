<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\User\Model\User;

/**
 * User backend observer model for passwords
 */
class AllowAdminUserOldPassword implements ObserverInterface
{

    /**
     * Save current admin password to prevent its usage when changed in the future.
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {

        /* @var $user User */
        $user = $observer->getEvent()->getObject();

        if ($user->getImportedPassword()) {
            $user->setPassword($user->getImportedPassword());
        }
    }
}
