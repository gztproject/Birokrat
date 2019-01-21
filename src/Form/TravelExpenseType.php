<?php 
// src/Form/TravelExpenseType.php
namespace App\Form;

use App\Entity\TravelExpense\TravelExpense;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\Type\DateTimePickerType;

class TravelExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date', DateTimePickerType::class,[
                'label' => 'label.date'
            ]) 
            ->add('travelStops', CollectionType::class, [
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
        	'data_class' => TravelExpense::class,
        ));
    }
}
