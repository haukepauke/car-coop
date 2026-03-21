<?php

namespace App\Service;

use App\Entity\Car;

class CarReviewService
{
    private const ALL_TIME_START = '2000-01-01';
    private const ALL_TIME_END   = '2099-12-31';

    /**
     * Build the full review data array for a car.
     *
     * @return array{
     *     userBalances: array<array{user: \App\Entity\User, balance: float}>,
     *     paymentProposals: array<array{from: \App\Entity\User, to: \App\Entity\User, amount: float}>,
     *     totalBalance: float,
     *     actualCostPerUnit: float,
     *     priceAdjustment: array<string, mixed>|null,
     * }
     */
    public function buildReviewData(Car $car): array
    {
        $allTimeStart = new \DateTime(self::ALL_TIME_START);
        $allTimeEnd   = new \DateTime(self::ALL_TIME_END);

        $users = $car->getActiveUsers()->toArray();

        $userBalances = array_map(fn($u) => [
            'user'    => $u,
            'balance' => round($u->getBalance(), 2),
        ], $users);

        $paymentProposals  = $this->computePaymentProposals($userBalances);
        $totalBalance      = round(array_sum(array_column($userBalances, 'balance')), 2);
        $actualCostPerUnit = $car->getCalculatedCosts($allTimeStart, $allTimeEnd);
        $priceAdjustment   = $this->computePriceAdjustment($car, $actualCostPerUnit);

        $activeUserTypes = array_values(array_filter(
            $car->getUserTypes()->toArray(),
            fn($ut) => $ut->isActive() && $ut->getPricePerUnit() > 0
        ));

        return [
            'userBalances'     => $userBalances,
            'paymentProposals' => $paymentProposals,
            'totalBalance'     => $totalBalance,
            'actualCostPerUnit' => round($actualCostPerUnit, 3),
            'activeUserTypes'  => $activeUserTypes,
            'priceAdjustment'  => $priceAdjustment,
        ];
    }

    /**
     * Greedy debt-settlement: propose the minimal set of payments to bring all balances to
     * the same level (the average). Works regardless of whether all balances are positive,
     * all negative, or mixed.
     *
     * Users below the average (relatively worse off) make payments.
     * Users above the average (relatively better off) receive payments.
     *
     * Returns an empty array if the total transfer amount is < 5 % of the sum of absolute
     * balances (i.e. the inequality is negligible).
     *
     * @param array<array{user: \App\Entity\User, balance: float}> $userBalances
     * @return array<array{from: \App\Entity\User, to: \App\Entity\User, amount: float}>
     */
    public function computePaymentProposals(array $userBalances): array
    {
        if (count($userBalances) < 2) {
            return [];
        }

        $balanceValues  = array_column($userBalances, 'balance');
        $avg            = array_sum($balanceValues) / count($balanceValues);
        $sumAbsBalances = array_sum(array_map('abs', $balanceValues));

        $debtors   = [];
        $creditors = [];

        foreach ($userBalances as $entry) {
            $deviation = round($entry['balance'] - $avg, 2);
            if ($deviation > 0.01) {
                $creditors[] = ['user' => $entry['user'], 'amount' => $deviation];
            } elseif ($deviation < -0.01) {
                $debtors[] = ['user' => $entry['user'], 'amount' => -$deviation];
            }
        }

        $totalTransfer = array_sum(array_column($debtors, 'amount'));
        if ($sumAbsBalances < 0.01 || ($totalTransfer / $sumAbsBalances) < 0.05) {
            return [];
        }

        usort($debtors,   fn($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $proposals = [];
        $i = 0;
        $j = 0;
        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = round(min($debtors[$i]['amount'], $creditors[$j]['amount']), 2);
            if ($amount >= 0.01) {
                $proposals[] = [
                    'from'   => $debtors[$i]['user'],
                    'to'     => $creditors[$j]['user'],
                    'amount' => $amount,
                ];
            }
            $debtors[$i]['amount']   -= $amount;
            $creditors[$j]['amount'] -= $amount;
            if ($debtors[$i]['amount'] < 0.01) {
                $i++;
            }
            if ($creditors[$j]['amount'] < 0.01) {
                $j++;
            }
        }

        return $proposals;
    }

    /**
     * Compute a price-per-unit adjustment proposal, or null if no adjustment is needed.
     *
     * No proposal is made when the average price of non-occasional user types is already
     * within 5 % of the actual cost per unit.
     *
     * The adjustment percentage is derived from regular (non-occasional-use) user types only,
     * then applied uniformly to all active user types. Suggested prices are rounded up to
     * 2 decimal places.
     *
     * @return array<string, mixed>|null
     */
    public function computePriceAdjustment(Car $car, float $actualCostPerUnit): ?array
    {
        if ($actualCostPerUnit <= 0) {
            return null;
        }

        $activeUserTypes = array_values(array_filter(
            $car->getUserTypes()->toArray(),
            fn($ut) => $ut->isActive() && $ut->getPricePerUnit() > 0
        ));

        if (count($activeUserTypes) === 0) {
            return null;
        }

        // Use only non-occasional user types to determine the adjustment percentage,
        // since occasional-use groups drive very few km and would skew the calculation.
        $regularUserTypes = array_values(array_filter($activeUserTypes, fn($ut) => !$ut->isOccasionalUse()));
        $typesForAvg      = count($regularUserTypes) > 0 ? $regularUserTypes : $activeUserTypes;
        $avgCurrentPrice  = array_sum(array_map(fn($ut) => $ut->getPricePerUnit(), $typesForAvg)) / count($typesForAvg);

        // Suppress proposal when current prices are already within 5 % of actual cost
        if ($avgCurrentPrice > 0 && abs($actualCostPerUnit - $avgCurrentPrice) / $avgCurrentPrice <= 0.05) {
            return null;
        }

        $adjustmentFactor = $avgCurrentPrice > 0 ? $actualCostPerUnit / $avgCurrentPrice : 1;

        $userTypesWithSuggested = array_map(fn($ut) => [
            'userType'  => $ut,
            'current'   => $ut->getPricePerUnit(),
            'suggested' => ceil($ut->getPricePerUnit() * $adjustmentFactor * 100) / 100,
        ], $activeUserTypes);

        $direction = $actualCostPerUnit > $avgCurrentPrice ? 'increase' : 'decrease';

        return [
            'direction'        => $direction,
            'adjustmentPercent' => round(($adjustmentFactor - 1) * 100, 1),
            'userTypes'        => $userTypesWithSuggested,
        ];
    }
}
