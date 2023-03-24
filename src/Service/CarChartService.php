<?php

namespace App\Service;

use App\Entity\Car;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class CarChartService
{
    private ChartBuilderInterface $chartBuilder;
    private TranslatorInterface $translator;

    public function __construct(
        ChartBuilderInterface $chartBuilder,
        TranslatorInterface $translator
    ) {
        $this->chartBuilder = $chartBuilder;
        $this->translator = $translator;
    }

    public function getDistanceDrivenByUserChart(Car $car, \DateTime $start = null, \DateTime $end = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $users = $car->getUsers();

        $labels = [];
        $colors = [];
        $mileAge = [];
        foreach ($users as $user) {
            $labels[] = $user->getName();
            $colors[] = $user->getColor();
            $mileAge[] = $user->getTripMileage($start, $end);
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

    public function getUserBalanceChart(Car $car, \DateTime $start = null, \DateTime $end = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $users = $car->getUsers();

        $labels = [];
        $colors = [];
        $balance = [];
        $moneySpent = [];
        foreach ($users as $user) {
            $labels[] = $user->getName();
            $colors[] = $user->getColor();
            $balance[] = $user->getBalance();
            $moneySpent[] = $user->getMoneySpent($start, $end);
        }

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->translator->trans('money.balance'),
                    'backgroundColor' => '#000',
                    'data' => $balance,
                    'hoverOffset' => 4,
                ],
                [
                    'label' => $this->translator->trans('money.spent').' '.(new \DateTime('now'))->format('Y'),
                    'backgroundColor' => '#999',
                    'data' => $moneySpent,
                    'hoverOffset' => 4,
                ],
            ],
        ]);

        $chart->setOptions([
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => $this->translator->trans('money.user.balance'),
                ],
            ],
        ]);

        return $chart;
    }
}
