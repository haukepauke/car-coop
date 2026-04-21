<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class CarChartService
{
    private ChartBuilderInterface $chartBuilder;
    private TranslatorInterface $translator;
    private Security $security;

    public function __construct(
        ChartBuilderInterface $chartBuilder,
        TranslatorInterface $translator,
        Security $security,
    ) {
        $this->chartBuilder = $chartBuilder;
        $this->translator = $translator;
        $this->security = $security;
    }

    public function getDistanceDrivenByUserChart(Car $car, ?\DateTime $start = null, ?\DateTime $end = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $users = $car->getActiveUsers();

        $labels = [];
        $colors = [];
        $mileAge = [];
        foreach ($users as $user) {
            $labels[] = $user->getName();
            $colors[] = $user->getColor();
            $mileAge[] = $user->getTripMileage($car, $start, $end);
        }

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'backgroundColor' => $colors,
                    'data' => $mileAge,
                    'hoverOffset' => 4,
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => false,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => $this->translator->trans('mileage.per.user').' '.(new \DateTime('now'))->format('Y'),
                ],
            ],
        ]);

        return $chart;
    }

    public function getUserBalanceChart(Car $car, ?\DateTime $start = null, ?\DateTime $end = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $users = $car->getActiveUsers();
        $themePalette = $this->getThemePalette();

        $labels = [];
        $balance = [];
        $moneySpent = [];
        foreach ($users as $user) {
            $labels[] = $user->getName();
            $balance[] = $user->getBalance($car);
            $moneySpent[] = $user->getMoneySpent($car, $start, $end);
        }

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->translator->trans('money.balance'),
                    'backgroundColor' => $themePalette['balanceFill'],
                    'borderColor' => $themePalette['balanceBorder'],
                    'borderWidth' => 1,
                    'data' => $balance,
                    'hoverOffset' => 4,
                ],
                [
                    'label' => $this->translator->trans('money.spent').' '.(new \DateTime('now'))->format('Y'),
                    'backgroundColor' => $themePalette['spentFill'],
                    'borderColor' => $themePalette['spentBorder'],
                    'borderWidth' => 1,
                    'data' => $moneySpent,
                    'hoverOffset' => 4,
                ],
            ],
        ]);

        $chart->setOptions([
            'scales' => [
                'x' => [
                    'ticks' => [
                        'color' => $themePalette['axisText'],
                    ],
                    'grid' => [
                        'color' => $themePalette['grid'],
                    ],
                ],
                'y' => [
                    'ticks' => [
                        'color' => $themePalette['axisText'],
                    ],
                    'grid' => [
                        'color' => $themePalette['grid'],
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'color' => $themePalette['legendText'],
                    ],
                ],
                'title' => [
                    'display' => true,
                    'text' => $this->translator->trans('money.user.balance'),
                    'color' => $themePalette['titleText'],
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * @return array{
     *     balanceFill: string,
     *     balanceBorder: string,
     *     spentFill: string,
     *     spentBorder: string,
     *     axisText: string,
     *     legendText: string,
     *     titleText: string,
     *     grid: string
     * }
     */
    private function getThemePalette(): array
    {
        $user = $this->security->getUser();
        $theme = $user instanceof User ? $user->getThemePreference() : 'light';

        return match ($theme) {
            'dark' => [
                'balanceFill' => 'rgba(255, 120, 222, 0.72)',
                'balanceBorder' => '#ff78de',
                'spentFill' => 'rgba(120, 217, 255, 0.58)',
                'spentBorder' => '#78d9ff',
                'axisText' => '#f5dff1',
                'legendText' => '#f5dff1',
                'titleText' => '#f5dff1',
                'grid' => 'rgba(214, 190, 210, 0.18)',
            ],
            'light' => [
                'balanceFill' => 'rgba(212, 59, 184, 0.72)',
                'balanceBorder' => '#9d1f85',
                'spentFill' => 'rgba(99, 198, 243, 0.62)',
                'spentBorder' => '#0f3b55',
                'axisText' => '#251525',
                'legendText' => '#251525',
                'titleText' => '#251525',
                'grid' => 'rgba(110, 82, 108, 0.18)',
            ],
            default => [
                'balanceFill' => 'rgba(30, 41, 59, 0.78)',
                'balanceBorder' => '#0f172a',
                'spentFill' => 'rgba(14, 165, 233, 0.60)',
                'spentBorder' => '#0369a1',
                'axisText' => '#1e293b',
                'legendText' => '#1e293b',
                'titleText' => '#1e293b',
                'grid' => 'rgba(71, 85, 105, 0.16)',
            ],
        };
    }
}
