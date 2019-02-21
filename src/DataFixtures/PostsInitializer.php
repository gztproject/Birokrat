<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Post;

class PostsInitializer
{      
    public function generate(ObjectManager $manager, array $countries): array
    {
        $posts = array();
        
        foreach ($countries as $country) {
        
            $path = __DIR__ . "/InitData/posts-".strtolower($country->getA2()).".csv";
            $fileReader = new ImportFileReader();
            $rows = $fileReader->GetRows($path);
            
            foreach ($rows as $row) {
                $post = new Post();
                $post->setName($row["Name"]);
                $post->setCode($row["Code"]);
                $post->setCodeInternational($row["CodeInternational"]);
                $post->setCountry($country);
                
        
                $manager->persist($post);
                
                array_push($posts, $post);
                
                $manager->flush();
            }
            
        }
        
        
        return $posts;
    }
}