<?php 
namespace App\Controller\Geography;

use App\Entity\Geography\Country;
use App\Form\Geography\PostType;
use App\Repository\CountryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Geography\CreatePostCommand;

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
    
    /**
     * Finds and displays the Country entity and it's posts.
     *
     * @Route("/dashboard/codesheets/country/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods={"GET"}, name="country_show")
     */
    public function show(Country $country): Response
    {    	
    	$c = new CreatePostCommand();
    	$postForm = $this->createForm(PostType::class, $c);
    	return $this->render('dashboard/codesheets/country/show.html.twig', [
    			'country' => $country,
    			'postForm' => $postForm->createView()
    	]);
    }
}