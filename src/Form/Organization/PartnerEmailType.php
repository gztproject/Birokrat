<?php

namespace App\Form\Organization;

use App\Entity\Organization\CreatePartnerEmailCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartnerEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('email', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'email'],
                'row_attr' => ['class' => 'col-sm-4'],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'row_attr' => ['class' => 'col-sm-3'],
            ])
            ->add('role', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'row_attr' => ['class' => 'col-sm-4'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreatePartnerEmailCommand::class,
        ]);
    }
}
