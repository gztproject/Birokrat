<?php

namespace App\Entity\Transaction;

use App\Entity\Konto\Konto;

final class AllocationPlanner
{
    /**
     * @param iterable<int, object|array> $allocations
     * @return list<array{konto: Konto, amount: float, note: ?string}>
     */
    public static function lines(?Konto $defaultDebit, float $total, iterable $allocations = []): array
    {
        $lines = [];
        foreach ($allocations as $allocation) {
            $konto = is_array($allocation) ? ($allocation['konto'] ?? null) : ($allocation->konto ?? null);
            $amount = (float) (is_array($allocation) ? ($allocation['amount'] ?? 0) : ($allocation->amount ?? 0));
            $note = is_array($allocation) ? ($allocation['note'] ?? null) : ($allocation->note ?? null);
            if ($konto instanceof Konto && round($amount, 2) > 0) {
                $lines[] = ['konto' => $konto, 'amount' => round($amount, 2), 'note' => $note];
            }
        }

        if ($lines === []) {
            if (!$defaultDebit instanceof Konto) {
                throw new \LogicException('Please set konto preferences for this organization before booking invoices.');
            }

            return [['konto' => $defaultDebit, 'amount' => round($total, 2), 'note' => null]];
        }

        $sum = array_sum(array_column($lines, 'amount'));
        if (round($sum, 2) !== round($total, 2)) {
            throw new \InvalidArgumentException('Allocations must sum to the document amount.');
        }

        return $lines;
    }
}
