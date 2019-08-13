<?php 
// src/Form/InvoiceType.php
namespace App\Form\Invoice;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Organization\Client;
use App\Entity\Organization\Organization;
use App\Entity\Invoice\CreateInvoiceCommand;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('issuer', EntityType::class, array(
        		'class' => Organization::class,
        		'choice_label' => 'name',
        		'expanded'=>false,
        		'multiple'=>false,
        		'label' => 'label.issuer',
        	))
        	->add('number', TextType::class,[
        		'label' => 'label.number'
        	])        	
        	->add('recepient', EntityType::class, array(
        			'class' => Client::class,
        			'choice_label' => 'name',
        			'expanded'=>false,
        			'multiple'=>false,
        			'label' => 'label.recepient',
        	))
            ->add('dateOfIssue', DateTimePickerType::class,[
                'label' => 'label.dateOfIssue',
            	'widget' => 'single_text',
            	'format' => 'dd. MM. yyyy',
            		
            	// prevents rendering it as type="date", to avoid HTML5 date pickers
            	'html5' => false,            	
            ])
            ->add('dueDate', DateTimePickerType::class,[
            		'label' => 'label.dueDate',
            		'widget' => 'single_text',
            		'format' => 'dd. MM. yyyy',
            		
            		// prevents rendering it as type="date", to avoid HTML5 date pickers
            		'html5' => false,
            ])
            
            ->add('dateServiceRenderedFrom', DateTimePickerType::class,[
            	'label' => 'label.dateServiceRenderedFrom',
            	'widget' => 'single_text',
            	'format' => 'dd. MM. yyyy',
            		
            	// prevents rendering it as type="date", to avoid HTML5 date pickers
            	'html5' => false,
            ])
            ->add('dateServiceRenderedTo', DateTimePickerType::class,[
            	'label' => 'label.dateServiceRenderedTo',
            	'widget' => 'single_text',
            	'format' => 'dd. MM. yyyy',
            	// adds a class that can be selected in JavaScript
            	'attr' => ['id' => 'dateServiceRenderedTo'],
            		
            	// prevents rendering it as type="date", to avoid HTML5 date pickers
            	'html5' => false,
            ])
            ->add('invoiceItemCommands', CollectionType::class, [
            		'entry_type' => InvoiceItemType::class,
            		//'entry_options' => ['label' => false],
            		'allow_add' => true,
            		'allow_delete' => true,
            		'label' => 'label.invoiceItem',
            		'by_reference' => false,
            ])
            ->add('discount', NumberType::class, array(
            		'label' => 'label.discount',
            		'attr' => ['class' => 'discountInput'],
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateInvoiceCommand::class,
        ));
    }
}
