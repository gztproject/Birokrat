<?php 
namespace App\Form\LunchExpense;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Form\Organization\MemberOrganizationType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class LunchExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        	->add('organization', MemberOrganizationType::class, [
        		'label' => 'label.organization',
        	])
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
    }
}
