<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends AbstractController
{    
    /**
     * @Route("/invoice", methods={"GET"}, name="invoice_index")
     */
    public function index()
    {
               
        return $this->render('dashboard/invoice/index.html.twig');
    }    
    
}