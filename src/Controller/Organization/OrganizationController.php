<?php 
namespace App\Controller\Organization;

use App\Form\Geography\AddressType;
use App\Form\Organization\OrganizationType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Organization;
use App\Entity\Organization\UpdateOrganizationCommand;
use App\Entity\Organization\OrganizationCodeFactory;
use App\Repository\Organization\OrganizationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Geography\Address;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\UpdateAddressCommand;

class OrganizationController extends AbstractController
{
    
    /**
     * @Route("/dashboard/organization", methods={"GET"}, name="organization_index")
     */
	public function index(): Response
    {
    	$myOrganizations = $this->getUser()->getOrganizations();
        
        return $this->render(
        		'dashboard/organization/index.html.twig', 
        		array(
        				'organizations' => $myOrganizations, 
        				"entity" => 'organization'
        				
        		)
        );
    }
    
    /**
     * @Route("/dashboard/organization/list", methods={"GET"}, name="organization_list")
     */
    public function list(OrganizationRepository $organizations): Response
    {
    	$myOrganizations = $organizations->findBy([], ['name' => 'DESC']);
    	
    	$orgDataArray = array();
    	
    	foreach($myOrganizations as $org)
    	{
    		$dto = ['id'=>$org->getId(), 'name'=>$org->getName()];
    		array_push($orgDataArray, $dto);
    	}
    	
    	return new JsonResponse(
    			array(
    					array(
    							'status'=>'ok',
    							'data'=>array(
    									'organizations'=>$orgDataArray    									
    							)    							
    					)    					
    			)
    	);
    }
    
    /**
     * @Route("/dashboard/organization/new", methods={"GET", "POST"}, name="organization_new")
     */
    public function new(Request $request, ManagerRegistry $doctrine)
    {
        $c = new CreateOrganizationCommand();
        $c->code = OrganizationCodeFactory::factory('App\Entity\Organization\Organization', $doctrine)->generate();
        
        $form = $this->createForm(OrganizationType::class, $c)
        		->add('address', AddressType::class);
                
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {  
        	
        	$adr = $doctrine->getRepository(Address::class)->findBy(['line1'=>$c->address->line1]);
        	
        	$address = null;
        	foreach($adr as $a)
        	{
        		if($a != null && $a->getLine2() == $c->address->line2 && $a->getPost() == $c->address->post)
        		{
        			$address = $a;
        		}
        	}
        	$entityManager = $doctrine->getManager();
        	if($address === null)
        	{
        		$post = $c->address->post;
        		$cmd = new CreateAddressCommand();
        		$cmd->line1 = $c->address->line1;
        		$cmd->line2 = $c->address->line2;
        		$cmd->post = $post;
        		$address = $post->createAddress($cmd, $this->getUser());
        		$entityManager->persist($address);
        	}
        	
        	$c->address = $address;
        	
        	$organization = $this->getUser()->createOrganization($c);
        	
        	$entityManager = $doctrine->getManager();
        	
        	$entityManager->persist($organization);
        	$entityManager->flush();
            return $this->redirectToRoute('organization_index');
        }
        
        return $this->render(
            '/dashboard/organization/new.html.twig',
            array(
            		'form' => $form->createView(), 
            		"entity" => 'organization'
            )
        );
    }
    
    /**
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="organization_show")
     */
    public function show(Organization $organization): Response
    {           
        return $this->render('dashboard/organization/show.html.twig', [
        		'organization' => $organization,
        		"entity" => 'organization',
        ]);
    }
    
    /**
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="organization_edit")
     */
    public function edit(Request $request, Organization $organization, ManagerRegistry $doctrine): Response
    {
    	$updateCommand = new UpdateOrganizationCommand();
    	$organization->mapTo($updateCommand);
        $form = $this->createForm(OrganizationType::class, $updateCommand);
        
        $c = new UpdateAddressCommand();
        $c->line1 = $updateCommand->address->getLine1();
        $c->line2 = $updateCommand->address->getLine2();
        $c->post = $updateCommand->address->getPost();
        
        $addressForm = $this->createForm(AddressType::class, $c);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) 
        {            
            $updateCommand = $form->getData(); 
            $adr = $doctrine->getRepository(Address::class)->findBy(['line1'=>$updateCommand->address->line1]);
            
            $address = null;
            foreach($adr as $a)
            {
            	if($a != null && $a->getLine2() == $updateCommand->address->line2 && $a->getPost() == $updateCommand->address->post)
            	{
            		$address = $a;
            	}
            }
            $entityManager = $doctrine->getManager();
            if($address === null)
            {
            	$post = $updateCommand->address->post;
            	$cmd = new CreateAddressCommand();
            	$cmd->line1 = $updateCommand->address->line1;
            	$cmd->line2 = $updateCommand->address->line2;
            	$cmd->post = $post;
            	$address = $post->createAddress($cmd, $this->getUser());
            	$entityManager->persist($address);
            }
            
            $updateCommand->address = $address;
            
            $organization->update($updateCommand, $this->getUser());                                   
            $doctrine->getManager()->persist($organization);            
            $doctrine->getManager()->flush();            
            $this->addFlash('success', 'organization.updated_successfully');            
            return $this->redirectToRoute('organization_edit', ['id' => $organization->getId()]);
        }
        
        return $this->render('dashboard/organization/edit.html.twig', [
            'organization' => $updateCommand,
            'form' => $form->createView(),
        	'addressForm' => $addressForm->createView(),
        	'entity' => 'organization',
        ]);
    }    
   
    
    /**
     * Deletes an organization entity.
     *
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="organization_delete")
     */
    public function delete(Request $request, Organization $organization, ManagerRegistry $doctrine): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('organization_index');
        }
                        
        $em = $doctrine->getManager();
        $em->remove($organization);
        $em->flush();
        
        $this->addFlash('success', 'organization.deleted_successfully');
        
        return $this->redirectToRoute('organization_index');
    }
}