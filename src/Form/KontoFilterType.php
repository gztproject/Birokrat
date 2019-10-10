<?php 
namespace App\Form;

use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

class KontoFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('kontoClasses', EntityType::class, array(
                'class' => KontoClass::class,
                'choice_label' => 'numberAndName',
                'expanded'=>false,
                'multiple'=>false,
        		'label' => 'label.kontoClass',
        ));
            
        $formModifier = function (FormInterface $form, KontoClass $class = null) {
            $categories = null === $class ? array() : $class->getCategories();
                
            $form->add('kontoCategories', EntityType::class, array(
                'class' => KontoCategory::class,
                'choices' => $categories,
                'choice_label' => 'numberAndName',
                'expanded'=>false,
                'multiple'=>false,
            	'label' => 'label.kontoCategory',
            ));
        };
        
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
                $data = $event->getData()[0];
                $class = $data;
                $formModifier($event->getForm(), $class);
        });
        
       $builder->get('kontoClasses')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($formModifier) {
                    // It's important here to fetch $event->getForm()->getData(), as
                    $class = $event->getForm()->getData();
                    
                    // since we've added the listener to the child, we'll have to pass on
                    // the parent to the callback functions!
                    $formModifier($event->getForm()->getParent(), $class);
        });                   
    }
}
