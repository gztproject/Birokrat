<?php

namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use App\Entity\Geography\Post;
use App\Entity\Geography\Country;

class PostsInitializer implements IEntityInitializer
{      
	private $manager;
	private $path;
	private $country;
	
	/**
	 * Post initializer
	 * @param ObjectManager $manager DB manager to use for storing entities
	 * @param string $path Relative path to .tsv file
	 * @param Country $country A 'Country' object for which to generate posts
	 */
	public function __construct(ObjectManager $manager, string $path, Country $country)
	{
		$this->manager = $manager;
		$this->path = __DIR__ . $path;
		$this->country = $country;
	}
	
	/**
	 * Generates posts for selected country	 
	 * @return array Array of generated posts for selected country
	 */
    public function generate(): array
    {
        $posts = array();                
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($this->path);
            
        foreach ($rows as $row) {
            $post = new Post();
            $post->setName($row["Name"]);
            $post->setCode($row["Code"]);
            $post->setCodeInternational($row["CodeInternational"]);
            $post->setCountry($this->country);
                
            $this->manager->persist($post);
                
            array_push($posts, $post);
                
            $this->manager->flush();
        }
        return $posts;
    }
}