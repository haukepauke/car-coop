<?php

namespace App\Service;

class MilestoneService
{
    private const HUNDRED_K_MILESTONES = [100000, 200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];
    private const REPEATING_MILESTONES = [111111, 222222, 333333, 444444, 555555, 666666, 777777, 888888, 999999];

    /**
     * Returns milestones crossed when driving from $startMileage to $endMileage.
     * A milestone is "crossed" if startMileage < milestone <= endMileage.
     *
     * @return array<array{value: int, type: string, boardKey: string}>
     */
    public function getCrossedMilestones(int $startMileage, int $endMileage): array
    {
        $crossed = [];

        foreach (self::HUNDRED_K_MILESTONES as $milestone) {
            if ($startMileage < $milestone && $endMileage >= $milestone) {
                $crossed[] = [
                    'value'    => $milestone,
                    'type'     => 'hundredk',
                    'boardKey' => 'board_system.milestone_' . (int) ($milestone / 1000) . 'k',
                ];
            }
        }

        foreach (self::REPEATING_MILESTONES as $milestone) {
            if ($startMileage < $milestone && $endMileage >= $milestone) {
                $crossed[] = [
                    'value'    => $milestone,
                    'type'     => 'repeating',
                    'boardKey' => 'board_system.milestone_repeating',
                ];
            }
        }

        usort($crossed, fn ($a, $b) => $a['value'] <=> $b['value']);

        return $crossed;
    }
}
