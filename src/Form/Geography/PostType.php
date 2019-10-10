<?php 
namespace App\Form\Geography;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Geography\CreatePostCommand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Geography\Country;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', TextType::class,[
                'label' => 'label.code'
            ])
            ->add('codeInternational', TextType::class,[
            		'label' => 'label.codeInternational'
            ])
            ->add('name', TextType::class,[
            	    'label' => 'label.name',
            ])  
            ->add('country', EntityType::class,[
            		'label' => 'label.country',
            		'class' => Country::class,
            		'choice_label' => 'name',
            		'expanded'=>false,
            		'multiple'=>false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        		'data_class' => CreatePostCommand::class,
        ));
    }
}