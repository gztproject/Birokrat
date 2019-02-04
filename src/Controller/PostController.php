<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Geography\Post;
use App\Repository\PostRepository;

class PostController extends AbstractController
{    
    /**
     * @Route("/dashboard/codesheets/post", methods={"GET"}, name="post_index")
     */
    public function index(PostRepository $posts): Response
    {                      
    	$posts = $posts->findAll();
    	return $this->render('dashboard/codesheets/post.html.twig', ['posts' => $posts]);
    } 
}