<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\KontoRepository;
use App\Repository\KontoClassRepository;
use App\Repository\KontoCategoryRepository;
use App\Form\KontoFilterType;

class KontoController extends AbstractController
{    
    /**
     * @Route("/codesheets/konto", methods={"GET", "POST"}, name="konto_index")
     */
    public function index(Request $request, KontoClassRepository $kontoClasses, KontoCategoryRepository $kontoCategoties, KontoRepository $kontos): Response
    {                      
        $myKontoClasses = $kontoClasses->findAll();
        $form = $this->createForm(KontoFilterType::class, $myKontoClasses);
        $form->handleRequest($request);        
        if ($form->isSubmitted() && $form->isValid()) {   
            return null;
        }
        
        if(isset($request->request->get("konto_filter")["kontoClasses"]))
        {            
            return $this->render('dashboard/codesheets/konto/_filter.html.twig', ['form' => $form->createView()]);
        }
        
        if(isset($request->request->get("konto_filter")["kontoCategories"]))
        {
            $myKontos = $kontos->findBy(["category" => $request->request->get("konto_filter")["kontoCategories"]]);
            return $this->render('dashboard/codesheets/konto/_table.html.twig', ['kontos' => $myKontos]);
        }
        
        $myKontos = $kontos->findAll();
        return $this->render('dashboard/codesheets/konto/konto.html.twig', ['kontos' => $myKontos, 'form'=>$form->createView()]);
            
    } 
}