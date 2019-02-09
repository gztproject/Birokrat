<?php 
namespace App\Controller;

use App\Repository\CountryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CountryController extends AbstractController
{    
    /**
     * @Route("/dashboard/codesheets/country", methods={"GET"}, name="country_index")
     */
    public function index(CountryRepository $countries): Response
    {                      
    	$countries = $countries->findAll();
    	return $this->render('dashboard/codesheets/country/country.html.twig', ['countries' => $countries]);
    } 
}