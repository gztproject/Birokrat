<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Settings\UserSettingsRepository")
 */
class UserSettings extends Base
{
    /**
     * @ORM\Id()
     * @ORM\OneToOne(targetEntity="App\Entity\User\User", inversedBy="userSettings", cascade={"persist", "remove"})
     */
    private $user;

    /**
     * 
     * @param CreateUserSettingsCommand $c
     * @param User $user
     * @param User $creator
     */
    public function __construct(CreateUserSettingsCommand $c, User $user, User $creator)
    {
    	parent::__construct($creator);
    	$this->user = $user;
    }
    
    /**
     * 
     * @param UpdateUserSettingsCommand $c
     * @param User $user
     * @throws \ErrorException
     */
    public function update(UpdateUserSettingsCommand $c, User $user)
    {
    	throw new \ErrorException("Not implemented yet.");
    	parent::updateBase($user);    	
    }
    
    
    
    public function getUser(): ?User
    {
        return $this->user;
    }
}
