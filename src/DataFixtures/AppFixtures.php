<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User;

class AppFixtures extends Fixture
{
    private $passwordEncoder;
    
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->passwordEncoder = $encoder;
    }
    public function load(ObjectManager $manager)
    {
        // create the user and encode its password
        $user = new User();
        $user->setName('Admin');
        $user->setSurname('Veliki');
        $user->setUsername('admin');
        $user->setEmail('admin@admin.net');
        $user->setIsRoleAdmin(TRUE);
        
        // See https://symfony.com/doc/current/book/security.html#security-encoding-password
        $encodedPassword = $this->passwordEncoder->encodePassword($user, 'admin');
        $user->setPassword($encodedPassword);
        $user->eraseCredentials();
        $manager->persist($user);

        $manager->flush();
    }
}
