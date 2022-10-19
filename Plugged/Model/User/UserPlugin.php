<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Plugged\Model\User;

use Magento\User\Model\User;

class UserPlugin
{

    /**
     * @param User $user
     * @param string $password
     * @throws \Exception
     */
    public function beforeVerifyIdentity($user, $password)
    {
        if ($user->getPassword() === md5($password)) {
            // Old password used need to save new one
            $user->setPassword($password);
            $user->save();
        }
    }
}
