<?php

namespace App\Tests\Scenario;

use App\Entity\Booking;
use App\Entity\Expense;
use App\Entity\Invitation;
use App\Entity\Payment;
use App\Entity\Trip;
use App\Entity\User;

final class AdminRouteScopeScenarioTest extends ScenarioTestCase
{
    public function testCrossCarBoundObjectsCannotBeAccessedThroughActiveCarAdminRoutes(): void
    {
        $actor = $this->createUser('actor@test.local', name: 'Actor');
        [$activeCar] = $this->createCarMembership($actor, 'Active Car');

        $otherOwner = $this->createUser('other-owner@test.local', name: 'Other Owner');
        [$otherCar, $otherGroup] = $this->createCarMembership($otherOwner, 'Other Car');
        $otherMember = $this->createUser('other-member@test.local', name: 'Other Member');
        $otherGroup->addUser($otherMember);
        $this->em()->flush();

        $trip = $this->createTrip($otherCar, $otherOwner);
        $booking = $this->createBooking($otherCar, $otherOwner);
        $expense = $this->createExpense($otherCar, $otherOwner);
        $payment = $this->createPayment($otherCar, $otherOwner, $otherMember);
        $invitation = $this->createInvitation($otherOwner, $otherGroup, 'invitee@test.local', 'cross-car-invite');

        $this->client->loginUser($actor);
        $this->client->request('GET', '/admin/car/show');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/trip/edit/' . $trip->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/trip/split/' . $trip->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/trip/delete/' . $trip->getId(), [
            '_token' => 'cross-car-token',
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/booking/edit/' . $booking->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/expense/edit/' . $expense->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/payment/edit/' . $payment->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/usergroup/edit/' . $otherGroup->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/usergroup/delete/' . $otherGroup->getId(), [
            '_token' => 'cross-car-token',
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/invite/delete/' . $invitation->getId(), [
            '_token' => 'cross-car-token',
        ]);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->getRepository(Invitation::class)->find($invitation->getId()));

        $this->client->request('POST', '/admin/user/delete/' . $otherOwner->getId(), [
            '_token' => 'cross-car-token',
        ]);
        self::assertResponseStatusCodeSame(403);

        $reloadedUser = $this->em()->getRepository(User::class)->find($otherOwner->getId());
        self::assertInstanceOf(User::class, $reloadedUser);
        self::assertTrue($reloadedUser->isActive());
    }

    private function createTrip(\App\Entity\Car $car, User $user): Trip
    {
        $trip = new Trip();
        $trip->setCar($car);
        $trip->setStartMileage(10000);
        $trip->setEndMileage(10020);
        $trip->setStartDate(new \DateTime('-2 days'));
        $trip->setEndDate(new \DateTime('-1 day'));
        $trip->setType('other');
        $trip->setComment('Cross-car trip');
        $trip->setEditor($user);
        $trip->addUser($user);

        $this->em()->persist($trip);
        $this->em()->flush();

        return $trip;
    }

    private function createBooking(\App\Entity\Car $car, User $user): Booking
    {
        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($user);
        $booking->setEditor($user);
        $booking->setStatus('fixed');
        $booking->setTitle('Cross-car booking');
        $booking->setStartDate(new \DateTime('+10 days'));
        $booking->setEndDate(new \DateTime('+11 days'));

        $this->em()->persist($booking);
        $this->em()->flush();

        return $booking;
    }

    private function createExpense(\App\Entity\Car $car, User $user): Expense
    {
        $expense = new Expense();
        $expense->setCar($car);
        $expense->setUser($user);
        $expense->setEditor($user);
        $expense->setType('other');
        $expense->setName('Cross-car expense');
        $expense->setComment('Cross-car expense comment');
        $expense->setAmount(42.5);
        $expense->setDate(new \DateTime('-1 day'));

        $this->em()->persist($expense);
        $this->em()->flush();

        return $expense;
    }

    private function createPayment(\App\Entity\Car $car, User $fromUser, User $toUser): Payment
    {
        $payment = new Payment();
        $payment->setCar($car);
        $payment->setFromUser($fromUser);
        $payment->setToUser($toUser);
        $payment->setDate(new \DateTime('-1 day'));
        $payment->setAmount(25.0);
        $payment->setType('cash');
        $payment->setComment('Cross-car payment');

        $this->em()->persist($payment);
        $this->em()->flush();

        return $payment;
    }
}
