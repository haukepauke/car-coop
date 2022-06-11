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
                    'label' => '2022',
                    'backgroundColor' => $colors,
                    'data' => $mileAge,
                    'hoverOffset' => 4,
                ],
            ],
        ]);

        $chart->setOptions([
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Mileage per User',
                ],
            ],
        ]);

        return $chart;
    }
}
