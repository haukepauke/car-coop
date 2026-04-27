<?php

namespace App\Tests\Scenario;

use App\Entity\Booking;

class BookingEditScenarioTest extends ScenarioTestCase
{
    public function testEndedBookingsRenderReadOnlyAndRejectManualPost(): void
    {
        $user = $this->createUser('booking-ended@example.com');
        [$car] = $this->createCarMembership($user);

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setEditor($user);
        $booking->setStatus('fixed');
        $booking->setTitle('Ended booking');
        $booking->setStartDate(new \DateTime('-3 days 10:00'));
        $booking->setEndDate(new \DateTime('-2 days 10:00'));
        $this->em()->persist($booking);
        $this->em()->flush();

        $this->loginThroughForm('booking-ended@example.com', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/booking/edit/' . $booking->getId());

        self::assertSame('disabled', $crawler->filterXPath('//*[@name="booking_form[startDate]"]')->attr('disabled'));
        self::assertSame('disabled', $crawler->filterXPath('//*[@name="booking_form[endDate]"]')->attr('disabled'));
        self::assertSame('disabled', $crawler->filterXPath('//*[@name="booking_form[title]"]')->attr('disabled'));
        self::assertSame('disabled', $crawler->filterXPath('//*[@name="booking_form[status]"]')->attr('disabled'));
        self::assertSame('disabled', $crawler->filterXPath('//*[@name="booking_form[user]"]')->attr('disabled'));
        self::assertCount(0, $crawler->filterXPath('//button[contains(normalize-space(), "Update Booking")]'));

        $this->client->request('POST', '/admin/booking/edit/' . $booking->getId(), [
            'booking_form' => [
                'startDate' => (new \DateTimeImmutable('-3 days 10:00'))->format('Y-m-d\TH:i'),
                'endDate' => (new \DateTimeImmutable('+1 day 10:00'))->format('Y-m-d\TH:i'),
                'title' => 'Tampered ended booking',
                'status' => 'maybe',
                'user' => (string) $user->getId(),
            ],
        ]);
        $this->followRedirectChain();

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Booking::class)->find($booking->getId());
        self::assertInstanceOf(Booking::class, $reloaded);
        self::assertSame('Ended booking', $reloaded->getTitle());
        self::assertSame('fixed', $reloaded->getStatus());
        self::assertSame((new \DateTimeImmutable('-2 days 10:00'))->format('Y-m-d H:i'), $reloaded->getEndDate()->format('Y-m-d H:i'));
    }

    public function testStartedButNotEndedBookingsCanStillBeUpdated(): void
    {
        $user = $this->createUser('booking-ongoing@example.com');
        [$car] = $this->createCarMembership($user);

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setEditor($user);
        $booking->setStatus('fixed');
        $booking->setTitle('Ongoing booking');
        $booking->setStartDate(new \DateTime('-2 hours'));
        $booking->setEndDate(new \DateTime('+1 day'));
        $this->em()->persist($booking);
        $this->em()->flush();

        $this->loginThroughForm('booking-ongoing@example.com', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/booking/edit/' . $booking->getId());
        $form = $crawler->filterXPath('//form')->form([
            'booking_form[startDate]' => $booking->getStartDate()->format('Y-m-d\TH:i'),
            'booking_form[endDate]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i'),
            'booking_form[title]' => 'Updated ongoing booking',
            'booking_form[status]' => 'maybe',
            'booking_form[user]' => (string) $user->getId(),
        ]);

        $this->client->submit($form);
        $this->followRedirectChain();

        self::assertResponseIsSuccessful();

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Booking::class)->find($booking->getId());
        self::assertInstanceOf(Booking::class, $reloaded);
        self::assertSame('Updated ongoing booking', $reloaded->getTitle());
        self::assertSame('maybe', $reloaded->getStatus());
        self::assertSame($booking->getStartDate()->format('Y-m-d H:i'), $reloaded->getStartDate()->format('Y-m-d H:i'));
    }

    public function testCreatingBookingInPastIsStillRejected(): void
    {
        $user = $this->createUser('booking-create@example.com');
        [$car] = $this->createCarMembership($user);

        $this->loginThroughForm('booking-create@example.com', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/booking/new');
        $form = $crawler->filterXPath('//form')->form([
            'booking_form[startDate]' => (new \DateTimeImmutable('-2 days 10:00'))->format('Y-m-d\TH:i'),
            'booking_form[endDate]' => (new \DateTimeImmutable('-1 day 10:00'))->format('Y-m-d\TH:i'),
            'booking_form[title]' => 'Past booking',
            'booking_form[status]' => 'fixed',
            'booking_form[user]' => (string) $user->getId(),
        ]);

        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Bookings cannot be made for the past.',
            $this->client->getResponse()->getContent()
        );
        self::assertSame(0, $this->em()->getRepository(Booking::class)->count([]));
    }
}
