<?php 
namespace App\Controller\Organization;

use App\Form\Geography\AddressType;
use App\Form\Organization\ClientType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Client;
use App\Repository\Organization\ClientRepository;
use App\Form\Geography\AddressDTO;
use App\Entity\Organization\OrganizationCodeFactory;

class ClientController extends AbstractController
{
    
    /**
     * @Route("/dashboard/client", methods={"GET"}, name="client_index")
     */
	public function index(ClientRepository $clients): Response
    {
        $myClients = $clients->findBy([], ['name' => 'DESC']);
        
        return $this->render('dashboard/organization/index.html.twig', ['organizations' => $myClients, 'entity' => 'client']);
    }
    
    /**
     * @Route("/dashboard/client/new", methods={"GET", "POST"}, name="client_new")
     */
    public function new(Request $request)
    {
        $client = new Client();
        
        $code = OrganizationCodeFactory::factory('App\Entity\Organization\Client', $this->getDoctrine())->generate();
        $client->setCode($code);
        
        $form = $this->createForm(ClientType::class, $client)
            ->add('saveAndCreateNew', SubmitType::class);
                
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {  
        	$entityManager = $this->getDoctrine()->getManager();
        	
        	$entityManager->persist($client);
        	$entityManager->flush();
            return $this->redirectToRoute('client_index');
        }
       
        $addressForm = $this->createForm(AddressType::class, new AddressDTO());  
        
        return $this->render(
            '/dashboard/organization/new.html.twig',
            array(
            		'form' => $form->createView(),
            		'addressForm' => $addressForm->createView(),
            		'entity' => 'client'
            )
        );
    }
    
    /**
     * @Route("/dashboard/client/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="client_show")
     */
    public function show(Client $client): Response
    {           
        return $this->render('dashboard/organization/show.html.twig', [
        		'organization' => $client,
        		'entity' => 'client'
        ]);
    }
    
    /**
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/dashboard/client/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="client_edit")
     */
    public function edit(Request $request, Client $organization): Response
    {
    	$form = $this->createForm(ClientType::class, $organization);
    	
    	$addressDTO = new AddressDTO();
    	$addressDTO->setLine1($organization->getAddress()->getLine1());
    	$addressDTO->setLine2($organization->getAddress()->getLine2());
    	$addressDTO->setPost($organization->getAddress()->getPost());
    	
    	$addressForm = $this->createForm(AddressType::class, $addressDTO);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            $client = $form->getData();
                                   
            $this->getDoctrine()->getManager()->persist($organization);
            
            $this->getDoctrine()->getManager()->flush();
            
            $this->addFlash('success', 'organization.updated_successfully');
            
            return $this->redirectToRoute('client_edit', ['id' => $organization->getId()]);
        }
        
        return $this->render('dashboard/client/edit.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        	'addressForm' => $addressForm->createView(),
        ]);
    }    
   
    
    /**
     * Deletes an organization entity.
     *
     * @Route("/dashboard/client/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="client_delete")
     */
    public function delete(Request $request, Client $client): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('client_index');
        }
                        
        $em = $this->getDoctrine()->getManager();
        $em->remove($client);
        $em->flush();
        
        $this->addFlash('success', 'organization.deleted_successfully');
        
        return $this->redirectToRoute('client_index');
    }
}