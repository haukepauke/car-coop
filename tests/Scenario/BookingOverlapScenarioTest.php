<?php

namespace App\Tests\Scenario;

use App\Entity\Booking;

class BookingOverlapScenarioTest extends ScenarioTestCase
{
    public function testCreatingAnOverlappingBookingShowsWarningButStillSaves(): void
    {
        $user = $this->createUser('booker@example.com');
        [$car] = $this->createCarMembership($user);

        $existingBooking = new Booking();
        $existingBooking->setCar($car);
        $existingBooking->setUser($user);
        $existingBooking->setEditor($user);
        $existingBooking->setStatus('fixed');
        $existingBooking->setTitle('Existing booking');
        $existingBooking->setStartDate(new \DateTime('+10 days 10:00'));
        $existingBooking->setEndDate(new \DateTime('+12 days 10:00'));
        $this->em()->persist($existingBooking);
        $this->em()->flush();

        $this->loginThroughForm('booker@example.com', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/booking/new');
        $form = $crawler->filterXPath('//form')->form([
            'booking_form[startDate]' => (new \DateTimeImmutable('+11 days 10:00'))->format('Y-m-d\TH:i'),
            'booking_form[endDate]' => (new \DateTimeImmutable('+13 days 10:00'))->format('Y-m-d\TH:i'),
            'booking_form[title]' => 'Overlapping booking',
            'booking_form[status]' => 'fixed',
            'booking_form[user]' => (string) $user->getId(),
        ]);

        $this->client->submit($form);
        $crawler = $this->followRedirectChain();

        self::assertNotNull($crawler);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'overlaps with 1 existing booking',
            $this->client->getResponse()->getContent()
        );
        self::assertSame(2, $this->em()->getRepository(Booking::class)->count([]));
    }
}
