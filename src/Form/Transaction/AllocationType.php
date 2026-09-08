<?php

namespace App\Form\Transaction;

use App\Entity\Konto\Konto;
use App\Entity\Transaction\CreateAllocationCommand;
use App\Repository\KontoRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AllocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('konto', EntityType::class, [
                'class' => Konto::class,
                'query_builder' => function (KontoRepository $repository) {
                    return $repository->createQueryBuilder('k')
                        ->leftJoin('k.category', 'c')
                        ->where('c.number = 04 OR c.number = 40 OR c.number = 41 OR c.number = 43')
                        ->orderBy('k.number', 'ASC');
                },
                'choice_label' => 'numberAndName',
                'required' => false,
                'label' => 'label.recievedIncomingInvoiceKonto',
            ])
            ->add('amount', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'label' => 'label.sum',
            ])
            ->add('note', TextType::class, [
                'required' => false,
                'label' => 'label.description',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateAllocationCommand::class,
        ]);
    }
}
