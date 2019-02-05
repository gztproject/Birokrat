<?php 
namespace App\Form;


use App\Entity\Invoice\InvoiceItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class InvoiceItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {    	
        $builder 
        ->add('code', TextType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'codeInput'],
        ))
        ->add('name', TextType::class, array(        		
        		'label' => false,
        		'attr' => ['class' => 'nameInput'],
        ))
        ->add('quantity', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'quantityInput'],
        ))
        ->add('unit', TextType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'unitInput'],
        ))
        ->add('value', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'valueInput'],
        ))
        ->add('discount', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'discountInput'],
        ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => InvoiceItem::class,
        ));
    }
}
