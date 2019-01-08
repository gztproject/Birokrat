<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Country;
use App\Entity\Post;

class PostsInitializer
{      
    public function generate(ObjectManager $manager, array $countries): array
    {
        $posts = array();
        
        //for...
        $post = new Post();
        $post->setName("Škofja Loka");
        $post->setCode("4220");
        $post->setCodeInternational("SI-4220");
        $post->setCountry(array_pop($countries));
                
        
        $manager->persist($post);
        array_push($posts, $post);
        $manager->flush();
        //end for...
        
        
        return $posts;
    }
}