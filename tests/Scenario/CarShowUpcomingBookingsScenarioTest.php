<?php

namespace App\Tests\Scenario;

use App\Entity\Booking;

final class CarShowUpcomingBookingsScenarioTest extends ScenarioTestCase
{
    public function testUpcomingBookingsAreSortedByClosestStartDateFirst(): void
    {
        $user = $this->createUser('car-show-bookings@test.local', name: 'Car Show Booker');
        [$car] = $this->createCarMembership($user, 'Sorted Car');

        $this->createBooking($car, $user, 'Later booking', '+8 days 10:00', '+8 days 12:00');
        $this->createBooking($car, $user, 'Sooner booking', '+3 days 10:00', '+3 days 12:00');

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/admin/car/show');

        self::assertResponseIsSuccessful();

        $titles = $crawler
            ->filterXPath('//table[contains(@class, "mobile-data-table")]/tbody/tr/td[4]')
            ->each(static fn($node) => trim($node->text()));

        self::assertSame(['Sooner booking', 'Later booking'], $titles);
    }

    private function createBooking(\App\Entity\Car $car, \App\Entity\User $user, string $title, string $startDate, string $endDate): void
    {
        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setEditor($user);
        $booking->setStatus('fixed');
        $booking->setTitle($title);
        $booking->setStartDate(new \DateTime($startDate));
        $booking->setEndDate(new \DateTime($endDate));

        $this->em()->persist($booking);
        $this->em()->flush();
    }
}
