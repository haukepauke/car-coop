<?php

namespace App\Service;

use App\Entity\Car;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

class CarPdfExportService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function generate(Car $car, string $locale): string
    {
        $now = new \DateTime();
        $firstDayOfYear = new \DateTime('first day of January');
        $lastDayOfYear  = new \DateTime('last day of December');

        $trips    = $car->getTrips()->toArray();
        $expenses = $car->getExpenses()->toArray();
        $payments = $car->getPayments()->toArray();
        $bookings = $car->getBookings()->toArray();

        usort($trips,    fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());
        usort($expenses, fn($a, $b) => $a->getDate() <=> $b->getDate());
        usort($payments, fn($a, $b) => $a->getDate() <=> $b->getDate());
        usort($bookings, fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());

        $totalDistance = array_sum(
            array_map(fn($t) => $t->isCompleted() ? $t->getMileage() : 0, $trips)
        );
        $totalExpenses = array_sum(array_map(fn($e) => $e->getAmount(), $expenses));
        $totalPayments = array_sum(array_map(fn($p) => $p->getAmount(), $payments));

        $context = [
            'car'                    => $car,
            'trips'                  => $trips,
            'expenses'               => $expenses,
            'payments'               => $payments,
            'bookings'               => $bookings,
            'exportDate'             => $now->format('d.m.Y H:i'),
            'distanceTravelled'      => $car->getDistanceTravelled($firstDayOfYear, $lastDayOfYear),
            'moneySpent'             => $car->getMoneySpent($firstDayOfYear, $lastDayOfYear),
            'moneySpentFuel'         => $car->getMoneySpent($firstDayOfYear, $lastDayOfYear, 'fuel'),
            'calculatedCostsPerUnit' => $car->getCalculatedCosts($firstDayOfYear, $lastDayOfYear),
            'totalDistance'          => $totalDistance,
            'totalExpenses'          => $totalExpenses,
            'totalPayments'          => $totalPayments,
        ];

        $html = $this->localeSwitcher->runWithLocale(
            $locale,
            fn() => $this->twig->render('admin/car/export.pdf.html.twig', $context)
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
