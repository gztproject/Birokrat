<?php 
// src/Form/TravelStopType.php
namespace App\Form;


use App\Entity\TravelExpense\TravelStop;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Geography\Post;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Controller\PostController;
use App\Repository\PostRepository;

class TravelStopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$pc = new PostController;
        $builder  
        	->add('stopOrder', TextType::class, array(
      			'label' => false,
        	))
            ->add('post', EntityType::class, array(
           		'class' => Post::class,
           		'choice_label' => 'name',
           		'expanded'=>false,
           		'multiple'=>false,
           		'label' => false,
            	//'preferred_choices' => $pc->getPreferredPosts()
            ))
            ->add('distanceFromPrevious', TextType::class, array(
            		'label' => false,
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => TravelStop::class,
        ));
    }
}
