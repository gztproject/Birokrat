<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\KontoRepository;

class KontoController extends AbstractController
{    
    /**
     * @Route("/codesheets/konto", methods={"GET"}, name="konto_index")
     */
    public function index(KontoRepository $kontos): Response
    {                      
        $myKontos = $kontos->findAll();
        return $this->render('dashboard/codesheets/konto.html.twig', ['kontos' => $myKontos]);
    } 
}