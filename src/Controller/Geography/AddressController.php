<?php 
namespace App\Controller\Geography;

use Doctrine\Persistence\ManagerRegistry;
use App\Form\Geography\AddressType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Geography\Address;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Geography\CreateAddressCommand;

class AddressController extends AbstractController
{    
    /**
     * @Route("/dashboard/address/new", methods={"POST"}, name="address_new")
     */
    public function newAddress(Request $request, ManagerRegistry $doctrine): Response
    {	
    	$c = new CreateAddressCommand();
    	$form = $this->createForm(AddressType::class, $c);    	
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$adr = $doctrine->getRepository(Address::class)->findOneBy(['line1'=>$c->line1]);
    		
    		if($adr != null && $adr->getLine2() == $c->line2 && $adr->getPost() == $c->post)
    		{
    			return new JsonResponse(array(array('status'=>'error','data'=>'This address already exists.')));
    		}
    		$post = $c->post;
    		$address = $post->createAddress($c, $this->getUser());
    		$entityManager = $doctrine->getManager();    		
    		$entityManager->persist($address);    		
    		$entityManager->flush();
    		
    		return new JsonResponse(
    				array(
    						array(
    								'status'=>'ok',
    								'data'=>array(
    										'address'=>array(
    												'id' => $address->getId(),
    												'fullAddress' => $address->getFullAddress(),
    												'line1' => $address->getLine1(),
     												'line2' => $address->getLine2(),
     												'post' => array(
     														'id' => $address->getPost()->getId(),
     														'name' => $address->getPost()->getName(),
     														'country' => $address->getPost()->getCountry()->getName()
     												),    												
    										)    										
    								)    								
    						)    						
    				)
    		);
    	}
    	return new JsonResponse(array(array('status'=>'error','data'=>'No data submitted')));
    }    
    
}