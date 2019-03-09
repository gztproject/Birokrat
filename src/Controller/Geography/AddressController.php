<?php 
namespace App\Controller\Geography;

use App\Form\Geography\AddressType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Geography\Address;
use App\Entity\Organization\Client;
use App\Repository\Organization\ClientRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class AddressController extends AbstractController
{    
    /**
     * @Route("/dashboard/address/new", methods={"POST"}, name="address_new")
     */
    public function newAddress(Request $request): Response
    {	
    	$address = new Address();
    	$form = $this->createForm(AddressType::class, $address);    	
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$adr = $this->getDoctrine()->getRepository(Address::class)->findOneBy(['line1'=>$address->getLine1()]);
    		
    		if($adr != null && $adr->getLine2() == $address->getLine2() && $adr->getPost() == $address->getPost())
    		{
    			return new JsonResponse(array(array('status'=>'error','data'=>'This address already exists.')));
    		}
    		
    		$entityManager = $this->getDoctrine()->getManager();    		
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
//     												'line1' => $address->getLine1(),
//     												'line2' => $address->getLine2(),
//     												'post' => array(
//     														'id' => $address->getPost()->getId(),
//     														'name' => $address->getPost()->getName(),
//     														'country' => $address->getPost()->getCountry()->getName()
//     												),    												
    										)    										
    								)    								
    						)    						
    				)
    		);
    	}
    	return new JsonResponse(array(array('status'=>'error','data'=>'No data submitted')));
    }    
    
}