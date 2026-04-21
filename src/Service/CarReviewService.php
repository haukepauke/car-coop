<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\UserType;

class CarReviewService
{
    private const ALL_TIME_START = '2000-01-01';
    private const ALL_TIME_END   = '2099-12-31';
    private const PRICE_ADJUSTMENT_THRESHOLD = 0.05;

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
            'balance' => round($u->getBalance($car), 2),
        ], $users);

        $paymentProposals  = $this->computePaymentProposals($this->buildUserBalances($this->getRegularUsers($car), $car));
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
     * The proposal is derived from the current balances and driven mileage of users in active
     * non-occasional driver groups. A common additive delta is applied to all active priced
     * groups so occasional-group gaps stay unchanged.
     *
     * No proposal is made when the current regular-group prices are already within 5 % of the
     * needed level.
     *
     * @return array<string, mixed>|null
     */
    public function computePriceAdjustment(Car $car, ?float $actualCostPerUnit = null): ?array
    {
        $regularUserTypes = $this->getRegularPricedUserTypes($car);
        if (count($regularUserTypes) === 0) {
            return null;
        }

        $crewUserType = $this->getCrewUserType($regularUserTypes);
        if ($crewUserType === null) {
            return null;
        }

        $activeUserTypes = $this->getActivePricedUserTypes($car);
        if (count($activeUserTypes) === 0) {
            return null;
        }

        $regularUsers = $this->getRegularUsers($car);
        if (count($regularUsers) < 2) {
            return null;
        }

        $actualCostPerUnit ??= $this->getAllTimeActualCostPerUnit($car);
        if ($actualCostPerUnit <= 0) {
            return null;
        }

        $crewCurrentPrice = $crewUserType->getPricePerUnit();
        if ($crewCurrentPrice <= 0) {
            return null;
        }

        $regularBalanceTotal = $this->getRegularUsersTotalBalance($car, $regularUsers);
        $regularMileageTotal = $this->getRegularUsersTotalMileage($car, $regularUsers);
        if ($regularMileageTotal <= 0) {
            return null;
        }

        $crewSuggestedPrice = max(0.0, $actualCostPerUnit + ($regularBalanceTotal / $regularMileageTotal));
        if (abs($crewSuggestedPrice - $crewCurrentPrice) / $crewCurrentPrice <= self::PRICE_ADJUSTMENT_THRESHOLD) {
            return null;
        }

        $deltaPerUnit = $crewSuggestedPrice - $crewCurrentPrice;

        $userTypesWithSuggested = array_map(function ($ut) use ($deltaPerUnit) {
            $suggested = $this->roundSuggestedPrice($ut->getPricePerUnit() + $deltaPerUnit, $deltaPerUnit);

            return [
                'userType'  => $ut,
                'current'   => $ut->getPricePerUnit(),
                'suggested' => $suggested,
            ];
        }, $activeUserTypes);

        $hasMeaningfulChange = count(array_filter(
            $userTypesWithSuggested,
            fn(array $entry) => abs($entry['suggested'] - $entry['current']) >= 0.01
        )) > 0;
        if (!$hasMeaningfulChange) {
            return null;
        }

        $direction = $deltaPerUnit > 0 ? 'increase' : 'decrease';

        return [
            'direction' => $direction,
            'adjustmentPercent' => round((($crewSuggestedPrice / $crewCurrentPrice) - 1) * 100, 1),
            'crewUserType' => $crewUserType,
            'crewCurrent' => $crewCurrentPrice,
            'crewSuggested' => $this->roundSuggestedPrice($crewSuggestedPrice, $deltaPerUnit),
            'regularBalanceTotal' => round($regularBalanceTotal, 2),
            'userTypes' => $userTypesWithSuggested,
        ];
    }

    /**
     * @param array<\App\Entity\User> $users
     * @return array<array{user: \App\Entity\User, balance: float}>
     */
    private function buildUserBalances(array $users, Car $car): array
    {
        return array_map(fn($user) => [
            'user' => $user,
            'balance' => round($user->getBalance($car), 2),
        ], $users);
    }

    /**
     * @return array<\App\Entity\UserType>
     */
    private function getActivePricedUserTypes(Car $car): array
    {
        return array_values(array_filter(
            $car->getUserTypes()->toArray(),
            fn($ut) => $ut->isActive() && $ut->getPricePerUnit() > 0
        ));
    }

    /**
     * @return array<\App\Entity\UserType>
     */
    private function getRegularPricedUserTypes(Car $car): array
    {
        return array_values(array_filter(
            $this->getActivePricedUserTypes($car),
            fn($ut) => !$ut->isOccasionalUse()
        ));
    }

    /**
     * @return array<\App\Entity\User>
     */
    private function getRegularUsers(Car $car): array
    {
        $usersById = [];

        foreach ($car->getUserTypes() as $userType) {
            if (!$userType->isActive() || $userType->isOccasionalUse()) {
                continue;
            }

            foreach ($userType->getUsers() as $user) {
                if ($user->isActive() && $user->getId() !== null) {
                    $usersById[$user->getId()] = $user;
                }
            }
        }

        return array_values($usersById);
    }

    /**
     * @param array<\App\Entity\User> $users
     */
    private function getRegularUsersTotalMileage(Car $car, array $users): int
    {
        return (int) array_sum(array_map(
            fn($user) => $user->getTripMileage($car),
            $users
        ));
    }

    /**
     * @param array<\App\Entity\User> $users
     */
    private function getRegularUsersTotalBalance(Car $car, array $users): float
    {
        return array_sum(array_map(
            fn($user) => $user->getBalance($car),
            $users
        ));
    }

    private function getAllTimeActualCostPerUnit(Car $car): float
    {
        return $car->getCalculatedCosts(
            new \DateTime(self::ALL_TIME_START),
            new \DateTime(self::ALL_TIME_END)
        );
    }

    /**
     * @param array<UserType> $regularUserTypes
     */
    private function getCrewUserType(array $regularUserTypes): ?UserType
    {
        foreach ($regularUserTypes as $userType) {
            if ($userType->getName() === 'Crew') {
                return $userType;
            }
        }

        return $regularUserTypes[0] ?? null;
    }

    private function roundSuggestedPrice(float $value, float $deltaPerUnit): float
    {
        $value = max(0.0, $value);
        $scaled = round($value * 100, 6);

        if ($deltaPerUnit > 0) {
            return ceil($scaled) / 100;
        }

        if ($deltaPerUnit < 0) {
            return floor($scaled) / 100;
        }

        return round($value, 2);
    }
}
