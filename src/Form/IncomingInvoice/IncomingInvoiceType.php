<?php 
// src/Form/IncomingInvoiceType.php
namespace App\Form\IncomingInvoice;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Organization\Client;
use App\Entity\Organization\Organization;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;

class IncomingInvoiceType extends AbstractType
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateIncomingInvoiceCommand::class,
        ));
    }
}
