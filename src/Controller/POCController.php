<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class POCController extends AbstractController
{    
    #[Route(path: "/POC/bootswatch", methods: ["GET"], name: "POC_bootswatch")]
    public function index()
    {
               
        return $this->render('dashboard/POC/index.html.twig');
    }   
    #[Route(path: "/POC/typeahead", methods: ["GET"], name: "POC_typeahead")]
    public function typeahead()
    {
               
        return $this->render('dashboard/POC/typeahead.html.twig');
    }    
    
}