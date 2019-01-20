<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class POCController extends AbstractController
{    
    /**
     * @Route("/POC", methods={"GET"}, name="POC_index")
     */
    public function index()
    {
               
        return $this->render('dashboard/POC/index.html.twig');
    }    
    
}