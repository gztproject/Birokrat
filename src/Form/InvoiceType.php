<?php 
// src/Form/InvoiceType.php
namespace App\Form;

use App\Entity\Invoice\Invoice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('number', TextType::class,[
        		'label' => 'label.number'
        	])
            ->add('dateOfIssue', DateTimePickerType::class,[
                'label' => 'label.date'
            ])
            ->add('invoiceItems', CollectionType::class, [
            		'entry_type' => InvoiceItemType::class,
            		//'entry_options' => ['label' => false],
            		'allow_add' => true,
            		'allow_delete' => true,
            		'label' => 'label.invoiceItem',
            		'by_reference' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => Invoice::class,
        ));
    }
}
