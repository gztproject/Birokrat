<?php 
namespace App\Controller;

use App\Form\UserType;
use App\Entity\User\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Organization\Organization;
use App\Repository\OrganizationRepository;

class OrganizationController extends AbstractController
{
    
    /**
     * @Route("/dashboard/organization", methods={"GET"}, name="organization_index")
     */
	public function index(OrganizationRepository $organizations): Response
    {
        $myOrganizations = $organizations->findBy([], ['name' => 'DESC']);
        
        return $this->render('dashboard/organization/index.html.twig', ['organizations' => $myOrganizations]);
    }
    
    /**
     * @Route("/dashboard/organization/new", methods={"GET", "POST"}, name="organization_new")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // 1) build the form
        $user = new User();
        $form = $this->createForm(UserType::class, $user)
            ->add('saveAndCreateNew', SubmitType::class);
        
        
        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
                        
            // 3) Encode the password (you could also do this via Doctrine listener)
            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);            
            $user->eraseCredentials();
            
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
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="organization_show")
     */
    public function show(Organization $organization): Response
    {           
        return $this->render('dashboard/organization/show.html.twig', [
        		'organization' => $organization,
        ]);
    }
    
    /**
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="organization_edit")
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
            
            $this->addFlash('success', 'organization.updated_successfully');
            
            return $this->redirectToRoute('organization_edit', ['id' => $user->getId()]);
        }
        
        return $this->render('dashboard/organization/edit.html.twig', [
            'organization' => $user,
            'form' => $form->createView(),
        ]);
    }    
   
    
    /**
     * Deletes an organization entity.
     *
     * @Route("/dashboard/organization/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/delete", methods={"POST"}, name="organization_delete")
     */
    public function delete(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('organization_index');
        }
                        
        $em = $this->getDoctrine()->getManager();
        $em->remove($user);
        $em->flush();
        
        $this->addFlash('success', 'organization.deleted_successfully');
        
        return $this->redirectToRoute('organization_index');
    }
}