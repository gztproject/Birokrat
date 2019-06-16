<?php 
namespace App\Controller;

use App\Form\User\CreateUserType;
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
use App\Entity\User\CreateUserCommand;

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
        $form = $this->createForm(CreateUserType::class, $createUserCommand)
            ->add('saveAndCreateNew', SubmitType::class);
        
        
        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
        	$user = $this->getUser()->createUser($createUserCommand, $passwordEncoder);
            
            // 4) save the User!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
            
            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the user
            
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
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="user_edit")
     * @Route("/admin/user/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="admin_user_edit")
     * @IsGranted("edit", subject="user", message="Users can only be edited by their authors.")
     */
    public function edit(Request $request, User $user): Response
    {
        if(!$user->getIsRoleAdmin())
            $user->setIsRoleAdmin(false);
            
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            $user = $form->getData();
                                   
            $this->getDoctrine()->getManager()->persist($user);
            
            $this->getDoctrine()->getManager()->flush();
            
            $this->addFlash('success', 'user.updated_successfully');
            
            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }
        
        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }  
    
    /**
     * @Route("/admin/user/addOrganization", methods={"POST"}, name="user_addOrganization")
     */
    public function setPaid(Request $request): Response
    {
    	$user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id'=>$request->request->get('userId', null)]);
    	$organization = $this->getDoctrine()->getRepository(Organization::class)->findOneBy(['id'=>$request->request->get('organizationId', null)]);
    	
    	$user->addOrganization($organization);
    	
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