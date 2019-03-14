<?php 

namespace App\DataFixtures;

interface IEntityInitializer
{
	public function generate(): array;
}