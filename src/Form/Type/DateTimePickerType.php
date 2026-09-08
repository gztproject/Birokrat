<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Date field using the native HTML5 date widget (replaces eonasdan/bootstrap-datetimepicker).
 */
class DateTimePickerType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'datetime',
        ]);
    }

    public function getParent(): string
    {
        return DateType::class;
    }
}
