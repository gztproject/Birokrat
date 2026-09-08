<?php 
namespace App\Form\LunchExpense;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\Organization\Organization;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class LunchExpenseType extends AbstractType
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
