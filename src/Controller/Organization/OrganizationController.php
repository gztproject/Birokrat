<?php 
namespace App\Controller\Organization;

use App\Form\Geography\AddressType;
use App\Form\Organization\OrganizationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Organization;
use App\Form\Geography\AddressDTO;
use App\Entity\Organization\OrganizationCodeFactory;
use App\Repository\Organization\OrganizationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrganizationController extends AbstractController
{
    
    /**
     * @Route("/dashboard/organization", methods={"GET"}, name="organization_index")
     */
	public function index(): Response
    {
    	$myOrganizations = $this->get('security.token_storage')->getToken()->getUser()->getOrganizations();
        
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
    public function new(Request $request)
    {
        $organization = new Organization();
        
        $code = OrganizationCodeFactory::factory('App\Entity\Organization\Organization', $this->getDoctrine())->generate();
        $organization->setCode($code);
        
        $form = $this->createForm(OrganizationType::class, $organization)
            ->add('saveAndCreateNew', SubmitType::class);
                
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {    
        	$entityManager = $this->getDoctrine()->getManager();
        	
        	$entityManager->persist($organization);
        	$entityManager->flush();
            return $this->redirectToRoute('organization_index');
        }        
       
        $addressForm = $this->createForm(AddressType::class, new AddressDTO());  
        
        return $this->render(
            '/dashboard/organization/new.html.twig',
            array(
            		'form' => $form->createView(), 
            		'addressForm' => $addressForm->createView(),
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
    public function edit(Request $request, Organization $organization): Response
    {
        $form = $this->createForm(OrganizationType::class, $organization);
        
        $addressDTO = new AddressDTO();
        $addressDTO->setLine1($organization->getAddress()->getLine1());
        $addressDTO->setLine2($organization->getAddress()->getLine2());
        $addressDTO->setPost($organization->getAddress()->getPost());
        
        $addressForm = $this->createForm(AddressType::class, $addressDTO);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            $organization = $form->getData();
                                   
            $this->getDoctrine()->getManager()->persist($organization);
            
            $this->getDoctrine()->getManager()->flush();
            
            $this->addFlash('success', 'organization.updated_successfully');
            
            return $this->redirectToRoute('organization_edit', ['id' => $organization->getId()]);
        }
        
        return $this->render('dashboard/organization/edit.html.twig', [
            'organization' => $organization,
            'form' => $form->createView(),
        	'addressForm' => $addressForm->createView(),
        ]);
    }    
   
    
    /**
     * Deletes an organization entity.
     *
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="organization_delete")
     */
    public function delete(Request $request, Organization $organization): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('organization_index');
        }
                        
        $em = $this->getDoctrine()->getManager();
        $em->remove($organization);
        $em->flush();
        
        $this->addFlash('success', 'organization.deleted_successfully');
        
        return $this->redirectToRoute('organization_index');
    }
}