<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User;
use App\Entity\Organization;

class UsersInitializer
{
    private $organizations;

    public function generate(ObjectManager $manager, UserPasswordEncoderInterface $encoder, array $organizations)
    {
        $this->organizations = $organizations;
        $path = __DIR__ . "/InitData/users.csv";
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($path);
                
        foreach ($rows as $row) {
            // create the user and encode its password
            $user = new User();
            $user->setFirstName($row["FirstName"]);
            $user->setLastName($row["LastName"]);
            $user->setUsername($row["UserName"]);
            $user->setEmail($row["EMail"]);
            $user->setMobile($row["Mobile"]);
            $user->setPhone($row["Phone"]);
            $user->setIsRoleAdmin($row["IsRoleAdmin"]==='TRUE');
            $user->setIsActive(true);
            
            $user->addOrganization($this->getOrganization($row["OrganizationCode"]));
        
            // See https://symfony.com/doc/current/book/security.html#security-encoding-password
            $encodedPassword = $encoder->encodePassword($user, $row["Password"]);
            $user->setPassword($encodedPassword);
            $user->eraseCredentials();
            $manager->persist($user);
        
            $manager->flush();
        }
    }
    
    private function getOrganization($code): Organization
    {
        foreach ($this->organizations as $organization) {
            if ($organization->getCode() === $code) {
                return $organization;
            }
        }
        return null;
    }
}