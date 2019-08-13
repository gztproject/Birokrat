<?php 
namespace App\Controller\User;

use App\Form\User\UserType;
use App\Entity\User\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Organization;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormError;

class UserController extends AbstractController
{
    
    /**
     * Lists all Users.
     *
     * This controller responds to two different routes with the same URL:
     *   * 'admin_user_index' is the route with a name that follows the same
     *     structure as the rest of the controllers of this class. 
     * @Route("/admin/user", methods={"GET"}, name="admin_user_index")
     */
    public function index(UserRepository $users): Response
    {
        $myUsers = $users->findBy(['isActive' => TRUE], ['username' => 'DESC']);
        
        return $this->render('admin/user/index.html.twig', ['users' => $myUsers]);
    }
    
    /**
     * @Route("/admin/user/new", methods={"GET", "POST"}, name="admin_user_new")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // 1) build the form
        $createUserCommand = new CreateUserCommand();
        $form = $this->createForm(UserType::class, $createUserCommand);        
        
        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
        	//Handle the file stuff
        	$signatureFile = $form['signature']->getData();        	
        	if($signatureFile)
        	{
        		$originalFilename = pathinfo($signatureFile->getClientOriginalName(), PATHINFO_FILENAME);
        		// this is needed to safely include the file name as part of the URL
        		$safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        		$newFilename = $safeFilename.'-'.uniqid().'.'.$signatureFile->guessExtension();
        		
        		// Move the file to the directory where brochures are stored
        		try {
        			$signatureFile->move(
        					$this->getParameter('signatures_directory'),
        					$newFilename
        					);
        		} catch (FileException $e) {
        			// ... handle exception if something happens during file upload
        		}        		
        		$createUserCommand->signatureFilename = $newFilename;
        	}
        	
        	try
        	{
        		$user = $this->getUser()->createUser($createUserCommand, $passwordEncoder);
        	}            
        	catch (\Exception $e)
        	{
        		$this->addFlash('danger', "Model Exception: ".$e->getMessage());
        		return $this->render(
        				'/admin/user/new.html.twig',
        				array('form' => $form->createView())
        				);
        	}
        	
        	
        	
            // 4) save the User!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
            
            // ... do any other work - like sending them an email, etc
            $this->addFlash('success', 'user.saved_successfully');
            
            return $this->redirectToRoute('admin_user_index');
        }
        
        return $this->render(
            '/admin/user/new.html.twig',
            array('form' => $form->createView())
            );
    }
    
    /**
     * Finds and displays a Invoice entity.
     *
     * @Route("/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="user_show")
     * @Route("/admin/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="admin_user_show")
     */
    public function show(User $user): Response
    {        
        $this->denyAccessUnlessGranted('show', $user, 'Invoices can only be shown to their authors.');
        
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }
    
    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="user_edit")
     * @Route("/admin/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="admin_user_edit")
     * @IsGranted("edit", subject="user", message="Users can only be edited by their authors.")
     */
    public function edit(Request $request, User $user, UserPasswordEncoderInterface $passwordEncoder): Response
    {
    	$c = new UpdateUserCommand();
    	$user->mapTo($c);
              
            
        $form = $this->createForm(UserType::class, $c);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
        	
        	//Handle the file stuff
        	$signatureFile = $form['signature']->getData();
        	if($signatureFile)
        	{
        		$originalFilename = pathinfo($signatureFile->getClientOriginalName(), PATHINFO_FILENAME);
        		// this is needed to safely include the file name as part of the URL
        		$safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        		$newFilename = $safeFilename.'-'.uniqid().'.'.$signatureFile->guessExtension();
        		
        		// Move the file to the directory where brochures are stored
        		try {
        			$signatureFile->move(
        					$this->getParameter('signatures_directory'),
        					$newFilename
        					);
        		} catch (FileException $e) {
        			// ... handle exception if something happens during file upload
        		}
        		$c->signatureFilename = $newFilename;
        	}
        	
        	try 
        	{
        		$user->update($c, $this->getUser(), $passwordEncoder);
        	} 
        	catch (\Exception $e) 
        	{
        		$this->addFlash('danger', "Model Exception: ".$e->getMessage());
        		return $this->render('admin/user/edit.html.twig', [
        				'user' => $user,
        				'form' => $form->createView(),
        				'showChangePassword' => true,
        		]);
        	}
            
                                   
            $this->getDoctrine()->getManager()->persist($user);
            
            $this->getDoctrine()->getManager()->flush();
            
            $this->addFlash('success', 'user.updated_successfully');
            
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        
        }
        
        if($form->isSubmitted() && !$form->isValid())
        {
        	foreach($form->getErrors(true) as $e)
        	{
        		$this->addFlash('danger', $e->getMessage());
        	}
        }
        
        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        	'showChangePassword' => $this->getUser() === $user,
        ]);
    }  
    
    /**
     * @Route("/admin/user/addOrganization", methods={"POST"}, name="user_addOrganization")
     */
    public function addOrganization(Request $request): Response
    {
    	$user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id'=>$request->request->get('userId', null)]);
    	$organization = $this->getDoctrine()->getRepository(Organization::class)->findOneBy(['id'=>$request->request->get('organizationId', null)]);
    	
    	$user->addOrganization($organization, $this->getUser());
    	
    	$entityManager = $this->getDoctrine()->getManager();
    	    	
    	
    	$entityManager->persist($user);
    	$entityManager->flush();
    	
    	return new JsonResponse(
    			array(
    					array(
    							'status'=>'ok',
    							'data'=>array(    									
    							)
    					)
    			)
    	);
    }
   
    
    /**
     * Deletes a User entity.
     *
     * @Route("/admin/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="admin_user_delete")
     * @IsGranted("delete", subject="user")
     */
    public function delete(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_user_index');
        }
                        
        $em = $this->getDoctrine()->getManager();
        $em->remove($user);
        $em->flush();
        
        $this->addFlash('success', 'user.deleted_successfully');
        
        return $this->redirectToRoute('admin_user_index');
    }
}