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

class AddressController extends AbstractController
{    
    /**
     * @Route("/dashboard/address/new", methods={"POST"}, name="address_new")
     */
    public function newAddress(Request $request)
    {	
    	$address = new Address();
    	$form = $this->createForm(AddressType::class, $address);
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    	}
    }    
    
}