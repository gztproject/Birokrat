<?php 
// src/Form/IncomingInvoiceType.php
namespace App\Form\IncomingInvoice;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use App\Entity\Organization\Partner;
use App\Entity\Organization\Organization;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\PaymentMethods;
use App\Entity\Konto\Konto;
use App\Repository\KontoRepository;
use App\Repository\Organization\PartnerRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class IncomingInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('issuer', EntityType::class, array(
        		'class' => Partner::class,
        		'query_builder' => function(PartnerRepository $repository) {
        			$qb = $repository->createQueryBuilder('p');
        			return $qb        			
        			->where('p.isSupplier = 1')
        			->orderBy('p.id', 'ASC')
        			;
        		},
        		'choice_label' => 'name',
        		'expanded'=>false,
        		'multiple'=>false,
        		'label' => 'label.issuer',
        	))
        	->add('number', TextType::class,[
        		'label' => 'label.number',
        	])     
        	->add('reference', TextType::class,[
        			'label' => 'label.reference',
					'required' => false,
        	]) 
        	->add('price', NumberType::class,[
        			'label' => 'label.price'
        	]) 
        	->add('recepient', EntityType::class, array(
        			'class' => Organization::class,
        			'choice_label' => 'name',
        			'expanded'=>false,
        			'multiple'=>false,
        			'label' => 'label.recepient',
        	))
            ->add('dateOfIssue', DateTimePickerType::class,[
                'label' => 'label.dateOfIssue',
            	'widget' => 'single_text',
            	'format' => 'dd. MM. yyyy',            		
            	'html5' => false,            	
            ])
            ->add('dueDate', DateTimePickerType::class,[
            		'label' => 'label.dueDate',
            		'widget' => 'single_text',
            		'format' => 'dd. MM. yyyy',            		
            		'html5' => false,
            		'required' => false,
            ])
            ->add('debitKonto', EntityType::class, array(
            		'class' => Konto::class,
            		'query_builder' => function(KontoRepository $repository) {
            			$qb = $repository->createQueryBuilder('k');
            			return $qb
            			->leftJoin('k.category', 'c')
            			->where('c.number = ?1 OR c.number = ?2')
            			->setParameter('1', '40')
            			->setParameter('2', '41')
            			->orderBy('k.number', 'ASC')
            			;
            		},
            		'choice_label' => 'numberAndName',
            		'expanded'=>false,
            		'multiple'=>false,
            		'label' => 'label.recievedIncomingInvoiceKonto',
            ))
            ->add('paymentMethod', ChoiceType::class, array(
            		'choices' => [
            				'label.cash' => PaymentMethods::cash,
            				'label.transaction' => PaymentMethods::transaction
            		],
            		'label' => 'label.paymentMethod',
            		'required' => false,
            ))
            ->add('paidOnSpot', CheckboxType::class, array(
            		'label' => 'label.paidOnSpot',
            		'required' => false,
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateIncomingInvoiceCommand::class,
        ));
    }
}
