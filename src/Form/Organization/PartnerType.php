<?php 
namespace App\Form\Organization;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use App\Entity\Geography\Address;
use App\Entity\Organization\CreatePartnerCommand;

class PartnerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class,[
                'label' => 'label.name'
            ])
            ->add('shortName', TextType::class,[
            		'label' => 'label.shortName',
            		'required' => false
            ])
            ->add('taxNumber', TextType::class,[
            		'label' => 'label.taxNumber'
            ])
            ->add('isClient', CheckboxType::class, array(
            		'label' => 'label.isClient',
            		'required' => false,
            ))
            ->add('isSupplier', CheckboxType::class, array(
            		'label' => 'label.isSupplier',
            		'required' => false,
            ))
            ->add('taxable', CheckboxType::class,[
            		'label' => 'label.taxable', 
            		'required' => false
            ])
            ->add('www', TextType::class,[
            		'label' => 'label.www',
            		'required' => false
            ])
            ->add('email', TextType::class,[
            		'label' => 'label.email',
            		'required' => false
            ])
            ->add('phone', TextType::class,[
            		'label' => 'label.phone',
            		'required' => false
            ])
            ->add('mobile', TextType::class,[
            		'label' => 'label.mobile',
            		'required' => false
            ])
            ->add('accountNumber', TextType::class,[
            		'label' => 'label.accountNumber',
            		'required' => false
            ])
            ->add('bic', TextType::class,[
            		'label' => 'label.bic',
            		'required' => false
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'data_class' => CreatePartnerCommand::class,
        ));
    }
}
