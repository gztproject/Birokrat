<?php 
namespace App\Form\Invoice;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Invoice\CreateInvoiceItemCommand;

class InvoiceItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {    	
        $builder 
        ->add('code', TextType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'form-control codeInput'],
        ))
        ->add('name', TextType::class, array(        		
        		'label' => false,
        		'attr' => ['class' => 'form-control nameInput'],
        ))
        ->add('quantity', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'form-control quantityInput'],
        ))
        ->add('unit', TextType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'form-control unitInput'],
        ))
        ->add('price', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'form-control priceInput'],
        ))
        ->add('discount', NumberType::class, array(
        		'label' => false,
        		'attr' => ['class' => 'form-control discountInput'],
        ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateInvoiceItemCommand::class,
        ));
    }
}
