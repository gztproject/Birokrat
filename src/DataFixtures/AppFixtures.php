<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
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
        //'internal' stuff
        $statesInitializer = new StatesInitializer();
        $statesInitializer->generate($manager);
        
        
        
        
        //actual data
        $kontosInitializer = new KontosInitializer();
        $kontosInitializer->generate($manager);
        
        $countryInitializer = new CountryInitializer();
        $countries = $countryInitializer->generate($manager);
        
        $postInitializer = new PostsInitializer();
        $posts = $postInitializer->generate($manager, $countries);
        
        $organizationsInitializer = new OrganizationsInitializer();
        $organizations = $organizationsInitializer->generate($manager, $posts);
        
        $usersInitilizer = new UsersInitializer();
        $usersInitilizer->generate($manager, $this->passwordEncoder, $organizations);
        
        //test data
    }
}
