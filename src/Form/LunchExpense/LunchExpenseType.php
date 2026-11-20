<?php 
namespace App\Form\LunchExpense;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Repository\Organization\OrganizationRepository;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class LunchExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];
        $builder
        	->add('organization', EntityType::class, array(
        		'class' => Organization::class,
        		'query_builder' => fn (OrganizationRepository $repository) => $repository->createMemberQueryBuilder($user),
        		'choice_label' => 'name',
        		'expanded'=>false,
        		'multiple'=>false,
        		'label' => 'label.organization',
        	))
            ->add('date', DateTimePickerType::class,[
            		'label' => 'label.date',
            ])
            ->add('sum', NumberType::class, [
            		'label' => 'label.sum',
            		'scale' => 2,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateLunchExpenseCommand::class,
        ));
        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', User::class);
    }
}
