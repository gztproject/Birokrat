<?php 
namespace App\Controller\Geography;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use App\Entity\Geography\Country;
use App\Entity\Geography\Post;
use App\Repository\PostRepository;
use App\Entity\Geography\CreatePostCommand;
use App\Form\Geography\PostType;

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
     * @Route("/dashboard/codesheets/post/new", methods={"POST"}, name="add_post")
     */
    public function addPost(Request $request): Response
    {
    	$c = new CreatePostCommand();
    	$form = $this->createForm(PostType::class, $c);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$post = $this->getDoctrine()->getRepository(Post::class)->findOneBy(['codeInternational'=>$c->codeInternational]);
    		
    		if($post != null && $post->getName() == $c->name && $post->getCountry() == $c->country)
    		{
    			return new JsonResponse(array(array('status'=>'error','data'=>'This post already exists.')));
    		}
    		$country = $c->country;
    		$post = $country->createPost($c, $this->getUser());
    		$entityManager = $this->getDoctrine()->getManager();
    		$entityManager->persist($post);
    		$entityManager->flush();
    		
    		return new JsonResponse(
    				array(
    						array(
    								'status'=>'ok',
    								'data'=>array(
    										'post'=>array(
    												'id' => $post->getId(),
    												'name' => $post->getName(),
    												'code' => $post->getCode(),
    												'codeInternational' => $post->getCodeInternational(),
    												'country' => array(
    														'id' => $post->getCountry()->getId(),
    														'name' => $post->getCountry()->getName(),
    												),
    										)
    								)
    						)
    				)
    				);
    	}
    	return new JsonResponse(array(array('status'=>'error','data'=>'No data submitted')));
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