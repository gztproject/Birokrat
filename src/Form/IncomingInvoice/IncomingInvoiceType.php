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
use App\Entity\IncomingInvoice\Enumerators\PaymentMethods;
use App\Entity\Konto\Konto;
use App\Repository\KontoRepository;
use App\Repository\Organization\PartnerRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\Transaction\AllocationType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;

class IncomingInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        	->add('issuer', EntityType::class, array(
        		'class' => Partner::class,
        		'query_builder' => function(PartnerRepository $repository) {
        			return $repository->suppliersRecentFirstQueryBuilder();
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
            ])
            ->add('dueDate', DateTimePickerType::class,[
            		'label' => 'label.dueDate',
            		'required' => false,
            ])
            ->add('debitKonto', EntityType::class, array(
            		'class' => Konto::class,
            		'query_builder' => function(KontoRepository $repository) {
            			$qb = $repository->createQueryBuilder('k');
            			return $qb
            			->leftJoin('k.category', 'c')
            			->where('c.number = 04 OR c.number = 40 OR c.number = 41 OR c.number = 43')
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
            				'label.transfer' => PaymentMethods::transaction
            		],
            		'label' => 'label.paymentMethod',
            		'required' => false,
            ))
            ->add('paidOnSpot', CheckboxType::class, array(
            		'label' => 'label.paidOnSpot',
            		'required' => false,
            ))
            ->add('bankCost', NumberType::class, [
            		'label' => 'label.bankCost',
            		'required' => false,
            		'scale' => 2,
            		'html5' => true,
            ])
            ->add('allocations', CollectionType::class, [
            		'entry_type' => AllocationType::class,
            		'allow_add' => true,
            		'allow_delete' => true,
            		'by_reference' => false,
            		'required' => false,
            		'label' => 'label.allocations',
            ])
            ->add('note', TextareaType::class, [
            		'label' => 'label.note',
            		'required' => false,
            		'attr' => ['rows' => 3],
            ])
            ->add('scan', FileType::class, [
            		'label' => 'label.scan',
            		'mapped' => false,
            		'required' => false,
            		'constraints' => [
            				new File(
            						maxSize: '8192k',
            						mimeTypes: [
            								'application/pdf',
            								'image/png',
            								'image/jpeg',
            						],
            						mimeTypesMessage: 'Please upload a valid PDF, PNG or JPG file',
            				)
            		],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateIncomingInvoiceCommand::class,
        ));
    }
}
