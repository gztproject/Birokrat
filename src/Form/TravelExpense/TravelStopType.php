<?php 
namespace App\Form\TravelExpense;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Geography\Post;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class TravelStopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$builder  
        	->add('stopOrder', NumberType::class, array(
      			'label' => false,
        		'attr' => ['class' => 'form-control StopOrder'],
        	))
            ->add('post', EntityType::class, array(
           		'class' => Post::class,
                'choice_label' => 'name', //'nameAndCode' -> can't type the name in this case
           		'expanded'=>false,
           		'multiple'=>false,
           		'label' => false,    
            	'attr' => ['class' => 'form-control'],
            ))
            ->add('distanceFromPrevious', NumberType::class, array(
            		'label' => false,
            		'attr' => ['class' => 'form-control'],
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateTravelStopCommand::class,
        ));
    }
}
