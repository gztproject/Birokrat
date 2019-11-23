<?php 
namespace App\Form\LunchExpense;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\Organization\Organization;

class LunchExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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
            		'widget' => 'single_text',
            		'format' => 'dd. MM. yyyy',
            ])            
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateLunchExpenseCommand::class,
        ));
    }
}
