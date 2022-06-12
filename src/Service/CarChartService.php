<?php

namespace App\Service;

use App\Entity\Car;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class CarChartService
{
    private ChartBuilderInterface $chartBuilder;

    public function __construct(ChartBuilderInterface $chartBuilder)
    {
        $this->chartBuilder = $chartBuilder;
    }

    public function getDistanceDrivenByUserChart(Car $car): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $users = $car->getUsers();

        $labels = [];
        $colors = [];
        $mileAge = [];
        foreach ($users as $user) {
            $labels[] = $user->getName();
            $colors[] = $user->getColor();
            $mileAge[] = $user->getTripMileage();
        }

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Balance',
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
                    'text' => 'Mileage per User',
                ],
            ],
        ]);

        return $chart;
    }

    public function getUserBalanceChart(Car $car): Chart
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
            $moneySpent[] = $user->getMoneySpent();
        }

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Balance',
                    'backgroundColor' => '#000',
                    'data' => $balance,
                    'hoverOffset' => 4,
                ],
                [
                    'label' => 'Money spent',
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
                    'text' => 'Balance per User',
                ],
            ],
        ]);

        return $chart;
    }
}
