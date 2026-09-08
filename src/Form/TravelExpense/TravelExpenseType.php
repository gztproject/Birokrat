<?php 
namespace App\Form\TravelExpense;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\Type\DateTimePickerType;
use App\Entity\Organization\Organization;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\User\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TravelExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        	->add('organization', EntityType::class, array(
        		'class' => Organization::class,
        		'choice_label' => 'name',
        		'expanded'=>false,
        		'multiple'=>false,
        		'label' => 'label.organization',
        	))
            ->add('date', DateTimePickerType::class,[
            		'label' => 'label.date',
            ])
            ->add('reason', TextType::class, [
            		'label' => 'label.reason',
            		'required' => false,
            ])
            ->add('advance', NumberType::class, [
            		'label' => 'label.advance',
            		'required' => false,
            		'scale' => 2,
            		'html5' => true,
            ]) 
        ;

        if ($options['is_admin']) {
        	$builder
        		->add('employee', EntityType::class, [
        			'class' => User::class,
        			'query_builder' => function (UserRepository $repository) {
        				return $repository->createQueryBuilder('u')
        					->where('u.isActive = :active')
        					->setParameter('active', true)
        					->orderBy('u.lastName', 'ASC')
        					->addOrderBy('u.firstName', 'ASC');
        			},
        			'choice_label' => 'fullname',
        			'expanded' => false,
        			'multiple' => false,
        			'label' => 'label.employee',
        		])
        		->add('rate', NumberType::class, [
        			'label' => 'label.travelExpenseRate',
        			'required' => false,
        			'scale' => 3,
        			'html5' => true,
        		]);
        }

        $builder
            ->add('travelStopCommands', CollectionType::class, [
            		'entry_type' => TravelStopType::class,
            		//'entry_options' => ['label' => false],
            		'allow_add' => true,
            		'allow_delete' => true,
            		'label' => 'label.travelStop',
            		'by_reference' => false,  
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateTravelExpenseCommand::class,
        	'is_admin' => false,
        ));
        $resolver->setAllowedTypes('is_admin', 'bool');
    }
}
