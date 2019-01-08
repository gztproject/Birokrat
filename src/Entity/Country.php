<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CountryRepository")
 */
class Country
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nameInt;

    /**
     * @ORM\Column(type="string", length=2)
     */
    private $A2;

    /**
     * @ORM\Column(type="string", length=3)
     */
    private $A3;

    /**
     * @ORM\Column(type="integer")
     */
    private $N3;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Post", mappedBy="country")
     */
    private $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getNameInt(): ?string
    {
        return $this->nameInt;
    }

    public function setNameInt(string $nameInt): self
    {
        $this->nameInt = $nameInt;

        return $this;
    }

    public function getA2(): ?string
    {
        return $this->A2;
    }

    public function setA2(string $A2): self
    {
        $this->A2 = $A2;

        return $this;
    }

    public function getA3(): ?string
    {
        return $this->A3;
    }

    public function setA3(string $A3): self
    {
        $this->A3 = $A3;

        return $this;
    }

    public function getN3(): ?int
    {
        return $this->N3;
    }

    public function setN3(int $N3): self
    {
        $this->N3 = $N3;

        return $this;
    }

    /**
     * @return Collection|Post[]
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts[] = $post;
            $post->setCountry($this);
        }

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->contains($post)) {
            $this->posts->removeElement($post);
            // set the owning side to null (unless already changed)
            if ($post->getCountry() === $this) {
                $post->setCountry(null);
            }
        }

        return $this;
    }
}
