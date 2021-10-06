<?php 
namespace App\Form\Transaction;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\DateTimePickerType;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\CreateTransactionCommand;
use Doctrine\DBAL\Types\DecimalType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use App\Entity\Konto\Konto;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('organization', EntityType::class, [
        			'class' => Organization::class,
        			'choice_label' => 'name',
        			'expanded'=>false,
        			'multiple'=>false,
        			'label' => 'label.organization',
        	])
            ->add('date', DateTimePickerType::class,[
            		'label' => 'label.date',
            		'widget' => 'single_text',
            		'format' => 'dd. MM. yyyy',
            ]) 
            ->add('debitKonto', EntityType::class, [
            		'class' => Konto::class,
            		'choice_label' => 'numberAndName',
            		'expanded'=>false,
            		'multiple'=>false,
            		'label' => 'label.debit',
            ])
            ->add('creditKonto', EntityType::class, array(
            		'class' => Konto::class,
            		'choice_label' => 'numberAndName',
            		'expanded'=>false,
            		'multiple'=>false,
            		'label' => 'label.credit',
            ))
            ->add('sum', NumberType::class, [
            		'label' => 'label.sum'
            ])
            ->add('description', TextareaType::class, [
            		'label' => 'label.description'
            ]) 
            ->add('hidden', CheckboxType::class, [
            		'label' => 'label.hidden',
                    'required' => false
            ]) 
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        	'data_class' => CreateTransactionCommand::class,
        	'allow_extra_fields' => true,
        ));
    }
}
