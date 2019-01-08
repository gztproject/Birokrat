<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AdminController extends AbstractController
{    
    /**         
     * @Route("/admin", methods={"GET"}, name="admin_index")
     */
    public function index()
    {   
        return $this->render('admin/index.html.twig');
    }    
    
}