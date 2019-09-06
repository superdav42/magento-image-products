<?php

namespace DevStone\ImageProducts\Plugged\Model\User;

class UserPlugin
{
	
  
    /**
	 * @param User $user
     * @param string $password
     */
    public function beforeVerifyIdentity($user, $password)
    {
		if($user->getPassword() === md5($password)) {
			// Old password used need to save new one
			$user->setPassword($password);
			$user->save();
		}
    }
  
}


