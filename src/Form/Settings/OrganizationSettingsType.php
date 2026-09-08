<?php
namespace App\Form\Settings;

use App\Entity\Konto\Konto;
use App\Entity\Settings\CreateOrganizationSettingsCommand;
use App\Repository\KontoRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganizationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('invoicePrefix', TextType::class, [
                'label' => 'label.invoicePrefix',
                'required' => false,
            ])
            ->add('defaultPaymentDueIn', IntegerType::class, [
                'label' => 'label.defaultPaymentDueIn',
                'required' => false,
            ])
            ->add('referenceModel', TextType::class, [
                'label' => 'label.referenceModel',
                'required' => false,
            ])
            ->add('travelExpenseRate', NumberType::class, [
                'label' => 'label.travelExpenseRate',
                'required' => false,
                'scale' => 3,
                'html5' => true,
            ])
            ->add('autoCreatePerDiem', CheckboxType::class, [
                'label' => 'label.autoCreatePerDiem',
                'required' => false,
            ])
            ->add('perDiemValue', NumberType::class, [
                'label' => 'label.perDiemValue',
                'required' => false,
                'scale' => 2,
                'html5' => true,
            ])
            ->add('autoCreateLunch', CheckboxType::class, [
                'label' => 'label.autoCreateLunch',
                'required' => false,
            ])
            ->add('lunchValue', NumberType::class, [
                'label' => 'label.lunchValue',
                'required' => false,
                'scale' => 2,
                'html5' => true,
            ]);

        foreach ($this->kontoFields() as $name => $label) {
            $builder->add($name, EntityType::class, [
                'class' => Konto::class,
                'query_builder' => function (KontoRepository $repository) {
                    return $repository->createQueryBuilder('k')
                        ->orderBy('k.number', 'ASC');
                },
                'choice_label' => 'numberAndName',
                'expanded' => false,
                'multiple' => false,
                'required' => false,
                'label' => $label,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateOrganizationSettingsCommand::class,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function kontoFields(): array
    {
        return [
            'IssueInvoiceDebit' => 'label.IssueInvoiceDebit',
            'IssueInvoiceCredit' => 'label.IssueInvoiceCredit',
            'InvoicePaidDebit' => 'label.InvoicePaidDebit',
            'InvoicePaidCredit' => 'label.InvoicePaidCredit',
            'IncurredTravelExpenseDebit' => 'label.IncurredTravelExpenseDebit',
            'IncurredTravelExpenseCredit' => 'label.IncurredTravelExpenseCredit',
            'PaidTravelExpenseDebit' => 'label.PaidTravelExpenseDebit',
            'PaidTravelExpenseCredit' => 'label.PaidTravelExpenseCredit',
            'ReceivedIncomingInvoiceDebit' => 'label.ReceivedIncomingInvoiceDebit',
            'ReceivedHomeIncomingInvoiceCredit' => 'label.ReceivedHomeIncomingInvoiceCredit',
            'ReceivedForeignIncomingInvoiceCredit' => 'label.ReceivedForeignIncomingInvoiceCredit',
            'PaidCashIncomingInvoiceCredit' => 'label.PaidCashIncomingInvoiceCredit',
            'PaidTransactionIncomingInvoiceCredit' => 'label.PaidTransactionIncomingInvoiceCredit',
            'RefundedIncomingInvoiceDebit' => 'label.RefundedIncomingInvoiceDebit',
            'RefundedIncomingInvoiceCredit' => 'label.RefundedIncomingInvoiceCredit',
            'RejectedIncomingInvoiceDebit' => 'label.RejectedIncomingInvoiceDebit',
            'RejectedIncomingInvoiceCredit' => 'label.RejectedIncomingInvoiceCredit',
            'BankFeeDebit' => 'label.BankFeeDebit',
            'BankFeeCredit' => 'label.BankFeeCredit',
            'PaidIncomingInvoiceDebit' => 'label.PaidIncomingInvoiceDebit',
            'PaidIncomingInvoiceCredit' => 'label.PaidIncomingInvoiceCredit',
        ];
    }
}
