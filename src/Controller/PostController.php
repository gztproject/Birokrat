<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use App\Repository\PostRepository;

class PostController extends AbstractController
{    
    /**
     * @Route("/dashboard/codesheets/post", methods={"GET"}, name="post_index")
     */
    public function index(PostRepository $posts): Response
    {                      
    	$posts = $posts->findAll();
    	return $this->render('dashboard/codesheets/post/post.html.twig', ['posts' => $posts]);
    } 

    /**
     * @Route("/api/post/list", methods={"GET"}, name="post_list")
     */
    public function listJson(PostRepository $posts): Response
    {
        $posts = $posts->findAll();

        $arrayCollection = array();

        foreach($posts as $item) {
            $arrayCollection[] = array(
            'id' => $item->getId(),
            'country_id' => $item->getCountry()->getId(),
            'code' => $item->getCode(),
            'codeInternational' => $item->getcodeInternational(),
            'name' => $item->getName()
            );
        }        
        
        $response = new JsonResponse(array(array('status'=>'ok','data'=>array('posts'=>$arrayCollection))));
        return $response;
    }

    /**
     * @Route("/api/post/listNames", methods={"GET"}, name="post_name_list")
     */
    public function listNamesJson(PostRepository $posts): Response
    {
        $posts = $posts->findAll();

        $arrayCollection = array();

        foreach($posts as $item) {
            $arrayCollection[] = $item->getName();
        }        
        return new JsonResponse($arrayCollection);
        $response = new JsonResponse(array(array('status'=>'ok','data'=>array('posts'=>$arrayCollection))));
        return $response;
    }
}