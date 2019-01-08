<?php 

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{    
    /**     
     * @Route("/dashboard", methods={"GET"}, name="dashboard_index")
     */
    public function index()
    {        
        return $this->render('dashboard/index.html.twig');
    }    
    
}
