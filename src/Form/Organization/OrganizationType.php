<?php 
namespace App\Form\Organization;

use App\Entity\Organization\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use App\Entity\Geography\Address;
use App\Entity\Organization\CreateOrganizationCommand;

class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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
            ->add('address', EntityType::class, [
            		'class' => Address::class,
            		'choice_label' => 'fullAddress',            		
            		'label' => 'label.address',
            ])
            ->add('taxable', CheckboxType::class,[
            		'label' => 'label.taxable', 'required' => false
            ])
            ->get('taxable')
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
            'data_class' => CreateOrganizationCommand::class,
        ));
    }
}
