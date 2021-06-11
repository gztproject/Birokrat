<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class AppFixtures extends Fixture
{   	
    private $passwordEncoder;
    
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->passwordEncoder = $encoder;
    }
        
    public function load(ObjectManager $manager)
    {        
        //actual data
    	$kontosInitializer = new KontosInitializer($manager, "/InitData/kontos.tsv");
        $kontosInitializer->generate();
        
        $countryInitializer = new CountryInitializer($manager, "/InitData/countries.tsv");
        $countries = $countryInitializer->generate();
        
        $posts = array();
        foreach ($countries as $country) {
        	$postInitializer = new PostsInitializer($manager, "/InitData/posts-".strtolower($country->getA2()).".tsv", $country);
        	array_push($posts, $postInitializer->generate());
        }
        
        $organizationsInitializer = new OrganizationsInitializer($manager, "/InitData/partners.tsv", "/InitData/organizations.tsv", $posts);
        $organizations = $organizationsInitializer->generate();
        
        $usersInitilizer = new UsersInitializer($manager, "/InitData/users.tsv", $organizations, $this->passwordEncoder);
        $usersInitilizer->generate();
        
        //test data
    }
}
