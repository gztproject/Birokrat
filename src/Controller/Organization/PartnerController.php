<?php 
namespace App\Controller\Organization;

use App\Form\Geography\AddressType;
use App\Form\Organization\PartnerType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Partner;
use App\Repository\Organization\PartnerRepository;
use App\Entity\Organization\OrganizationCodeFactory;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Geography\Address;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\UpdateAddressCommand;
use App\Entity\Organization\UpdatePartnerCommand;
use App\Entity\Organization\CreatePartnerEmailCommand;
use App\Mailer\RecipientListParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Organization\Organization;

class PartnerController extends AbstractController
{
    
    #[Route(path: "/dashboard/partner", methods: ["GET"], name: "partner_index")]
	public function index(PartnerRepository $partners): Response
    {
    	$myPartners = $partners->findBy([], ['name' => 'ASC']);
        
        return $this->render('dashboard/partner/index.html.twig', ['partners' => $myPartners]);
    }
    
    #[Route(path: "/dashboard/partner/new", methods: ["GET", "POST"], name: "partner_new")]
    public function new(Request $request, ManagerRegistry $doctrine)
    {
    	$c = new CreatePartnerCommand();                
        $c->code = OrganizationCodeFactory::factory('App\Entity\Organization\Partner', $doctrine)->generate();        
        $form = $this->createForm(PartnerType::class, $c)
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
        	
        	$partner = $this->getUser()->createPartner($c);
        	
        	
        	$entityManager->persist($partner);
        	$entityManager->flush();
            return $this->redirectToRoute('partner_index');
        }      
        
        
        return $this->render(
            '/dashboard/partner/new.html.twig',
            array(
            		'form' => $form->createView()
            )
        );
    }
    
    #[Route(path: "/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods: ["GET"], name: "partner_show")]
    public function show(Partner $partner): Response
    {       	
        return $this->render('dashboard/partner/show.html.twig', [
        		'partner' => $partner,
        ]);
    }
    
    #[Route(path: "/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit", methods: ["GET", "POST"], name: "partner_edit")]
    public function edit(Request $request, Partner $partner, ManagerRegistry $doctrine): Response
    {
    	$updateCommand = new UpdatePartnerCommand();
    	$partner->mapTo($updateCommand);
    	$addressCommand = new UpdateAddressCommand();
    	$partner->getAddress()->mapTo($addressCommand);
    	$updateCommand->address = $addressCommand;
    	if ($request->isMethod('GET')) {
    		$this->prefillRecipientQuery($updateCommand, $partner, (string) $request->query->get('to', ''), (string) $request->query->get('cc', ''));
    	}
    	$form = $this->createForm(PartnerType::class, $updateCommand)
    	->add('address', AddressType::class, ['data' => $addressCommand]);
    	
    	
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
            $partner->update($updateCommand, $this->getUser());
            $entityManager->persist($partner);            
            $entityManager->flush();            
            $this->addFlash('success', 'partner.updated_successfully');            
            return $this->redirectToRoute('partner_show', ['id' => $partner->getId()]);
        }
        
        //ToDo: What do we do with the drunken sailor? (well seriously, orphan addreses)
        
        return $this->render('dashboard/partner/edit.html.twig', [
            'partner' => $partner,
            'form' => $form->createView(),
        ]);
    }    
   
    
    #[Route(path: "/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods: ["POST"], name: "partner_delete")]
    public function delete(Request $request, Partner $partner, ManagerRegistry $doctrine): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('partner_index');
        }
                        
        $em = $doctrine->getManager();
        $em->remove($partner);
        $em->flush();
        
        $this->addFlash('success', 'partner.deleted_successfully');
        
        return $this->redirectToRoute('partner_index');
    }

    #[Route(path: "/dashboard/partner/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/emails/merge", methods: ["POST"], name: "partner_emails_merge")]
    public function mergeEmails(Request $request, Partner $partner, ManagerRegistry $doctrine): JsonResponse
    {
        $parser = new RecipientListParser();
        try {
            $to = $parser->parse((string) $request->request->get('to', ''));
            $cc = $parser->parse((string) $request->request->get('cc', ''));
            $partner->applyRecipientMerge($to, $cc, $this->getUser());
            $doctrine->getManager()->persist($partner);
            $doctrine->getManager()->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['status' => 'error', 'data' => [$e->getMessage()]], 400);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function prefillRecipientQuery(UpdatePartnerCommand $command, Partner $partner, string $to, string $cc): void
    {
        $parser = new RecipientListParser();
        if ($to !== '' && ($command->email === null || $command->email === '')) {
            $command->email = $to;
        }
        if ($cc === '') {
            return;
        }
        foreach ($parser->parse($cc) as $address) {
            if ($partner->hasMailbox($address->getAddress())) {
                continue;
            }
            $already = false;
            foreach ($command->extraEmailCommands as $existing) {
                if (strcasecmp((string) $existing->email, $address->getAddress()) === 0) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }
            $extra = new CreatePartnerEmailCommand();
            $extra->email = $address->getAddress();
            $extra->name = $address->getName() !== '' ? $address->getName() : null;
            $command->extraEmailCommands[] = $extra;
        }
    }

}