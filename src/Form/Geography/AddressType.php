<?php 
namespace App\Form\Geography;

use App\Entity\Geography\Post;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('line1', TextType::class,[
                'label' => 'label.line1'
            ])
            ->add('line2', TextType::class,[
            		'label' => 'label.line2',
            		'required' => false
            ])
            ->add('post', EntityType::class, array(
            		'class' => Post::class,
            		'choice_label' => 'name',
            		'expanded'=>false,
            		'multiple'=>false,
            		'label' => 'label.post',
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        		'data_class' => AddressDTO::class,
        ));
    }
}