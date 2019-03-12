<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User\User;
use App\Entity\Organization\Organization;
use Doctrine\ORM\EntityNotFoundException;

class UsersInitializer implements IEntityInitializer
{
	private $manager;
    private $organizations;
    private $path;
    private $encoder;
    
    /**
     * Users initializer
     * @param string $path Relative path to .tsv file
     * @param array $organizations An array of organizations
     * @param ObjectManager $manager DB manager to use for storing entities
     * @param UserPasswordEncoderInterface $encoder User password encoder
     */    
    public function __construct(ObjectManager $manager, string $path, array $organizations, UserPasswordEncoderInterface $encoder)
    {
    	$this->manager = $manager;
    	$this->path = __DIR__ . $path;
    	$this->organizations = $organizations;
    	$this->encoder = $encoder;
    }
    
    /**     
     * Generate users
     * @throws EntityNotFoundException Thrown when trying to create a user with nonexisting organization 
     * @return array Array of generated users 
     */
    public function generate(): array
    {               
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($this->path);
              
        $users = array();
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
            
            $org = $this->getOrganization($row["OrganizationCode"]);
            if($org==null)
            	throw new EntityNotFoundException('Organization with code '.$row["OrganizationCode"].' doesn\'t exist.');
            $user->addOrganization($org);
        
            // See https://symfony.com/doc/current/book/security.html#security-encoding-password
            $encodedPassword = $this->encoder->encodePassword($user, $row["Password"]);
            $user->setPassword($encodedPassword);
            $user->eraseCredentials();
            $this->manager->persist($user);
        	array_push($users, $user);
        	$this->manager->flush();
        }
        return $users;
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