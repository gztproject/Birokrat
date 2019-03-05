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
     * @ORM\OneToOne(targetEntity="App\Entity\User\User", inversedBy="userSettings", cascade={"persist", "remove"})
     */
    private $user;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
