<?php 
// src/Form/CreateUserType.php
namespace App\Form\User;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\CallbackTransformer;
use App\Entity\User\CreateUserCommand;
use Doctrine\DBAL\Types\StringType;

class CreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class,[
                'label' => 'label.email'
            ])
            ->add('mobile', TextType::class,[
            		'label' => 'label.mobile'
            ])
            ->add('username', TextType::class,[
                'label' => 'label.username'
            ])
            ->add('firstName', TextType::class,[
                'label' => 'label.firstname'
            ])
            ->add('lastName', TextType::class,[
                'label' => 'label.lastname'
            ])
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array('label' => 'label.password'),
                'second_options' => array('label' => 'label.repeat_password'),
            ))            
            ->add('isRoleAdmin', CheckboxType::class,[
                'label' => 'label.isRoleAdmin', 'required' => false
            ])
            ->get('isRoleAdmin')
                ->addModelTransformer(new CallbackTransformer(
                    function($boolToCheckbox){
                        
                        return $boolToCheckbox?:false;
                    },
                    function($checkboxToBool){
                        if($checkboxToBool===null)
                            return false;
                            return $checkboxToBool?:false;
                    }
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => CreateUserCommand::class,
        ));
    }
}