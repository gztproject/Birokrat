<?php

namespace App\Service\Ledger;

use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\TransactionRole;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Repository\KontoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankFeeFactory
{
    public function __construct(private readonly KontoRepository $kontos)
    {
    }

    public function createIfNeeded(
        Organization $organization,
        \DateTimeInterface $date,
        mixed $amount,
        User $user,
        ?iTransactionDocument $document,
        Transaction $related,
    ): ?Transaction {
        $fee = round((float) $amount, 2);
        if ($fee <= 0) {
            return null;
        }

        $settings = $organization->getOrganizationSettings();
        $debit = $settings->getBankFeeDebit() ?? $this->kontos->findOneBy(['number' => 415]);
        $credit = $settings->getBankFeeCredit() ?? $this->kontos->findOneBy(['number' => 110]);
        if ($debit === null || $credit === null) {
            throw new \LogicException('Set bank-fee kontos (or kontos 415 and 110) before booking bank costs.');
        }

        $command = new CreateTransactionCommand();
        $command->organization = $organization;
        $command->date = $date instanceof \DateTime ? $date : \DateTime::createFromInterface($date);
        $command->sum = $fee;
        $command->debitKonto = $debit;
        $command->creditKonto = $credit;
        $command->description = 'Stroški banke';
        $command->role = TransactionRole::BANK_FEE;
        $command->hidden = false;

        $transaction = new Transaction($command, $user, $document);
        $transaction->setRelatedTransaction($related);

        return $transaction;
    }

    public static function persist(EntityManagerInterface $em, Transaction|array $transactions): void
    {
        foreach (is_array($transactions) ? $transactions : [$transactions] as $transaction) {
            $em->persist($transaction);
        }
    }
}
