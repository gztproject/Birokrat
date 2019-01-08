<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User;

class UsersInitializer
{
    

    public function generate(ObjectManager $manager, UserPasswordEncoderInterface $encoder)
    {
        // create the user and encode its password
        $user = new User();
        $user->setName('Admin');
        $user->setSurname('Veliki');
        $user->setUsername('admin');
        $user->setEmail('admin@admin.net');
        $user->setIsRoleAdmin(TRUE);
        
        // See https://symfony.com/doc/current/book/security.html#security-encoding-password
        $encodedPassword = $encoder->encodePassword($user, 'admin');
        $user->setPassword($encodedPassword);
        $user->eraseCredentials();
        $manager->persist($user);
        
        $manager->flush();
    }
}