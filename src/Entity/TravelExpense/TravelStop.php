<?php

namespace App\Entity\TravelExpense;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Geography\Address;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelStopRepository")
 */
class TravelStop extends Base
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Post")
     */
    private $post;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Address")
     */
    private $address;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     */
    private $organization;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpense", inversedBy="travelStops")
     * @ORM\JoinColumn(nullable=false)
     */
    private $travelExpense;

    /**
     * @ORM\Column(type="integer")
     */
    private $stopOrder;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $distanceFromPrevious;

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

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

    public function getTravelExpense(): ?TravelExpense
    {
        return $this->travelExpense;
    }

    public function setTravelExpense(?TravelExpense $travelExpense): self
    {
        $this->travelExpense = $travelExpense;

        return $this;
    }

    public function getStopOrder(): ?int
    {
        return $this->stopOrder;
    }

    public function setStopOrder(int $stopOrder): self
    {
        $this->stopOrder = $stopOrder;

        return $this;
    }

    public function getDistanceFromPrevious()
    {
        return $this->distanceFromPrevious;
    }

    public function setDistanceFromPrevious($distanceFromPrevious): self
    {
        $this->distanceFromPrevious = $distanceFromPrevious;

        return $this;
    }
}
