<?php

namespace App\Tests\Api;

use App\Entity\Booking;

class BookingTest extends ApiTestCase
{
    protected static int $bookingId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $em   = static::em();
        $car  = $em->find(\App\Entity\Car::class,  static::$carId);
        $user = $em->find(\App\Entity\User::class, static::$userId);

        $booking = new Booking();
        $booking->setStartDate(new \DateTime('+30 days'));
        $booking->setEndDate(new \DateTime('+37 days'));
        $booking->setStatus('fixed');
        $booking->setTitle('API Test Booking');
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setEditor($user);
        $em->persist($booking);
        $em->flush();

        static::$bookingId = $booking->getId();
    }

    private function bookingIri(): string
    {
        return '/api/bookings/' . static::$bookingId;
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function testGetCollectionReturns200(): void
    {
        $response = static::authClient()->request('GET', '/api/bookings');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
    }

    public function testGetItemReturns200(): void
    {
        $response = static::authClient()->request('GET', $this->bookingIri());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(static::$bookingId, $data['id']);
        $this->assertSame('fixed', $data['status']);
        $this->assertSame('API Test Booking', $data['title']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        static::createClient()->request('GET', '/api/bookings');
        $this->assertResponseStatusCodeSame(401);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function testPostCreatesBooking(): void
    {
        $start = (new \DateTime('+60 days'))->format(\DateTime::ATOM);
        $end   = (new \DateTime('+67 days'))->format(\DateTime::ATOM);

        $response = static::authClient()->request('POST', '/api/bookings', [
            'json' => [
                'startDate' => $start,
                'endDate'   => $end,
                'status'    => 'maybe',
                'title'     => 'New booking via API',
                'car'       => static::carIri(),
                'user'      => static::userIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('maybe', $data['status']);
        $this->assertArrayHasKey('editor', $data);
    }

    public function testPostUnauthenticatedReturns401(): void
    {
        $start = (new \DateTime('+60 days'))->format(\DateTime::ATOM);
        $end   = (new \DateTime('+67 days'))->format(\DateTime::ATOM);

        static::createClient()->request('POST', '/api/bookings', [
            'json' => [
                'startDate' => $start,
                'endDate'   => $end,
                'status'    => 'fixed',
                'car'       => static::carIri(),
                'user'      => static::userIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    public function testPutUpdatesBooking(): void
    {
        $start = (new \DateTime('+30 days'))->format(\DateTime::ATOM);
        $end   = (new \DateTime('+40 days'))->format(\DateTime::ATOM);

        $response = static::authClient()->request('PUT', $this->bookingIri(), [
            'json' => [
                'startDate' => $start,
                'endDate'   => $end,
                'status'    => 'maybe',
                'title'     => 'Updated title',
                'car'       => static::carIri(),
                'user'      => static::userIri(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('maybe', $data['status']);
        $this->assertSame('Updated title', $data['title']);
    }

    public function testPutCannotReassignBookingToAnotherCar(): void
    {
        $start = (new \DateTime('+30 days'))->format(\DateTime::ATOM);
        $end   = (new \DateTime('+40 days'))->format(\DateTime::ATOM);

        static::authClient()->request('PUT', $this->bookingIri(), [
            'json' => [
                'startDate' => $start,
                'endDate'   => $end,
                'status'    => 'maybe',
                'title'     => 'Updated title',
                'car'       => static::otherCarIri(),
                'user'      => static::userIri(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $booking = static::em()->getRepository(Booking::class)->find(static::$bookingId);
        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertSame(static::$carId, $booking->getCar()?->getId());
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeleteBooking(): void
    {
        static::authClient()->request('DELETE', $this->bookingIri());
        $this->assertResponseStatusCodeSame(204);
    }
}
