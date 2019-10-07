<?php 
namespace App\Controller\Organization;

use App\Form\Geography\AddressType;
use App\Form\Organization\PartnerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Partner;
use App\Repository\Organization\PartnerRepository;
use App\Entity\Organization\OrganizationCodeFactory;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\UpdateAddressCommand;
use App\Entity\Organization\UpdatePartnerCommand;

class PartnerController extends AbstractController
{
    
    /**
     * @Route("/dashboard/partner", methods={"GET"}, name="partner_index")
     */
	public function index(PartnerRepository $partners): Response
    {
    	$myPartners = $partners->findBy([], ['name' => 'DESC']);
        
        return $this->render('dashboard/partner/index.html.twig', ['partners' => $myPartners]);
    }
    
    /**
     * @Route("/dashboard/partner/new", methods={"GET", "POST"}, name="partner_new")
     */
    public function new(Request $request)
    {
    	$c = new CreatePartnerCommand();                
        $c->code = OrganizationCodeFactory::factory('App\Entity\Organization\Partner', $this->getDoctrine())->generate();        
        $form = $this->createForm(PartnerType::class, $c);
                
        $form->handleRequest($request);        
        if ($form->isSubmitted() && $form->isValid()) {  
        	
        	$partner = $this->getUser()->createPartner($c);
        	$entityManager = $this->getDoctrine()->getManager();
        	
        	$entityManager->persist($partner);
        	$entityManager->flush();
            return $this->redirectToRoute('partner_index');
        }
       
        $addressForm = $this->createForm(AddressType::class, new CreateAddressCommand());  
        
        return $this->render(
            '/dashboard/partner/new.html.twig',
            array(
            		'form' => $form->createView(),
            		'addressForm' => $addressForm->createView()
            )
        );
    }
    
    /**
     * @Route("/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="partner_show")
     */
    public function show(Partner $partner): Response
    {           
        return $this->render('dashboard/partner/show.html.twig', [
        		'partner' => $partner,
        ]);
    }
    
    /**
     * Displays a form to edit an existing partner entity.
     *
     * @Route("/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="partner_edit")
     */
    public function edit(Request $request, Partner $partner): Response
    {
    	$updateCommand = new UpdatePartnerCommand();
    	$partner->mapTo($updateCommand);
    	$form = $this->createForm(PartnerType::class, $updateCommand);
    	
    	$addressDTO = new UpdateAddressCommand();
    	$addressDTO->line1 = $partner->getAddress()->getLine1();
    	$addressDTO->line2 = $partner->getAddress()->getLine2();
    	$addressDTO->post = $partner->getAddress()->getPost();
    	
    	$addressForm = $this->createForm(AddressType::class, $addressDTO);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) 
        {            
        	$updateCommand = $form->getData();
            $partner->update($updateCommand, $this->getUser());
            $this->getDoctrine()->getManager()->persist($partner);            
            $this->getDoctrine()->getManager()->flush();            
            $this->addFlash('success', 'partner.updated_successfully');            
            return $this->redirectToRoute('partner_edit', ['id' => $partner->getId()]);
        }
        
        return $this->render('dashboard/partner/edit.html.twig', [
            'partner' => $partner,
            'form' => $form->createView(),
        	'addressForm' => $addressForm->createView(),
        ]);
    }    
   
    
    /**
     * Deletes an organization entity.
     *
     * @Route("/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="partner_delete")
     */
    public function delete(Request $request, Partner $partner): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('partner_index');
        }
                        
        $em = $this->getDoctrine()->getManager();
        $em->remove($partner);
        $em->flush();
        
        $this->addFlash('success', 'partner.deleted_successfully');
        
        return $this->redirectToRoute('partner_index');
    }
}