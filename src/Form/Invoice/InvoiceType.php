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
use App\Repository\Organization\OrganizationRepository;
use App\Repository\Organization\PartnerRepository;
use App\Entity\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Organization\Partner;
use App\Entity\Organization\Organization;
use App\Entity\Invoice\CreateInvoiceCommand;

class InvoiceType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $member = $this->security->getUser();
        $member = $member instanceof User ? $member : null;

        $builder
        	->add('issuer', EntityType::class, array(
        		'class' => Organization::class,
        		'query_builder' => function(OrganizationRepository $repository) use ($member) {
        			return $repository->memberQueryBuilder($member);
        		},
        		'choice_label' => 'name',
        		'expanded'=>false,
        		'multiple'=>false,
        		'label' => 'label.issuer',
        	))
        	->add('number', TextType::class,[
        		'label' => 'label.number'
        	])        	
        	->add('recepient', EntityType::class, array(
        			'class' => Partner::class,
        			'query_builder' => function(PartnerRepository $repository) {
        				return $repository->clientsRecentFirstQueryBuilder();
        			},
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
            ])
            ->add('dateServiceRenderedFrom', DateTimePickerType::class,[
            	'label' => 'label.dateServiceRenderedFrom',
            ])
            ->add('dateServiceRenderedTo', DateTimePickerType::class,[
            	'label' => 'label.dateServiceRenderedTo',
            ])
            ->add('invoiceItemCommands', CollectionType::class, [
            		'entry_type' => InvoiceItemType::class,
            		'entry_options' => ['label' => false],
            		'allow_add' => true,
            		'allow_delete' => true,
            		'label' => false,
            		'by_reference' => false,
            ])
            ->add('discount', NumberType::class, array(
            		'label' => 'label.discount',
            		'attr' => ['class' => 'discountInput'],
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateInvoiceCommand::class,
        ));
    }
}
