<?php

namespace App\Form\Extension;

use App\Formatting\SlovenianFormat;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SlovenianFormatTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            NumberType::class,
            IntegerType::class,
            PercentType::class,
            MoneyType::class,
            DateType::class,
            DateTimeType::class,
            TimeType::class,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        if ($resolver->isDefined('locale')) {
            $resolver->setDefault('locale', SlovenianFormat::LOCALE);
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['lang'] = SlovenianFormat::LANGUAGE;
    }
}
