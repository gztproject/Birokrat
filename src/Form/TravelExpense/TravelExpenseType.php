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

class TravelExpenseType extends AbstractType
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
            ->add('createTravelStopCommands', CollectionType::class, [
            		'entry_type' => TravelStopType::class,
            		//'entry_options' => ['label' => false],
            		'allow_add' => true,
            		'allow_delete' => true,
            		'label' => 'label.travelStop',
            		'by_reference' => false,  
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateTravelExpenseCommand::class,
        ));
    }
}
