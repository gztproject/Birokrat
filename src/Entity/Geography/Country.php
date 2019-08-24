<?php

namespace App\Entity\Geography;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CountryRepository")
 */
class Country extends AggregateBase
{
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
     * @ORM\OneToMany(targetEntity="App\Entity\Geography\Post", mappedBy="country")
     */
    private $posts;

    public function __construct(CreateCountryCommand $c, User $user)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	
    	parent::__construct($user);
        $this->posts = new ArrayCollection();
        $this->A2 = $c->A2;
        $this->A3 = $c->A3;
        $this->N3 = $c->N3;
        $this->name = $c->name;
        $this->nameInt = $c->nameInt;
    }
    
    public function update(UpdateCountryCommand $c, User $user): Country
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
    
    public function createPost(CreatePostCommand $c, User $user): Post
    {
    	$post = new Post($c, $user, $this);
    	foreach($this->getPosts() as $p)
    	{
    		if ($p->getCodeInternational() == $post->getCodeInternational())
    			throw new \Exception("Post with this code already exists");
    	}
    	
    	$this->posts[] = $post;
    	return $post;    	
    }
    
    public function removePost(Post $post, User $user): ?Post
    {
    	if ($this->posts->contains($post)) {
    		$this->posts->removeElement($post);
    		// set the owning side to null (unless already changed)
    		if ($post->getCountry() === $this) {
    			return $post->removeCountry($this, $user);
    		}
    	}    	
    	return null;
    }
    
    public function updatePost(Post $post, UpdatePostCommand $c, User $user): ?Post
    {
    	if ($this->posts->contains($post)) {    	
    		return $post->update($c, $user);
    	}
    	return null;
    }
    

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getNameInt(): ?string
    {
        return $this->nameInt;
    }

    public function getA2(): ?string
    {
        return $this->A2;
    }

    public function getA3(): ?string
    {
        return $this->A3;
    }

    public function getN3(): ?int
    {
        return $this->N3;
    }
    
    /**
     * @return Collection|Post[]
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }    
}
