<?php

namespace App\Entity\Geography;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Organization\Organization;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AddressRepository")
 */
class Address extends Base
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $line1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $line2;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Post", inversedBy="addresses")
     * @ORM\JoinColumn(nullable=false)
     */
    private $post;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization", inversedBy="address")
     */
    private $organization;

    public function getLine1(): ?string
    {
        return $this->line1;
    }

    public function setLine1(string $line1): self
    {
        $this->line1 = $line1;

        return $this;
    }

    public function getLine2(): ?string
    {
        return $this->line2;
    }

    public function setLine2(?string $line2): self
    {
        $this->line2 = $line2;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }
    
    public function getFullAddress(): string
    {
    	$address = $this->line1;
    	if ($this->line2)
    		$address .= ", " . $this->line2;
    	$address .= ", " . $this->post->getNameAndCode() . ", " . $this->post->getCountry()->getName();
    	return $address;
    }
    
    public function getFullFormattedAddress(): array
    {
    	return explode(", ", $this->getFullAddress());    	
    }
}
