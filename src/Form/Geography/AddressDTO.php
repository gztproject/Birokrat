<?php
namespace App\Form\Geography;

use App\Entity\Geography\Post;

class AddressDTO
{
	private $line1;
	private $line2;
	private $post;
	
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
}