<?php

namespace App\CalDAV;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\CarRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarBackend extends AbstractBackend
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingRepository $bookingRepository,
        private readonly CarRepository $carRepository,
        private readonly UserRepository $userRepository,
        private readonly AuthBackend $authBackend,
    ) {}

    // -------------------------------------------------------------------------
    // Calendar collection methods
    // -------------------------------------------------------------------------

    public function getCalendarsForUser($principalUri)
    {
        $user = $this->getUserFromPrincipal($principalUri);
        if (!$user) {
            return [];
        }

        $calendars = [];
        foreach ($user->getUserTypes() as $userType) {
            if (!$userType->isActive()) {
                continue;
            }

            $car = $userType->getCar();
            $ctag = $this->computeCtag($car->getId());

            $calendars[] = [
                'id'                                                               => $car->getId(),
                'uri'                                                              => 'car-' . $car->getId(),
                'principaluri'                                                     => $principalUri,
                '{DAV:}displayname'                                                => $car->getName(),
                '{urn:ietf:params:xml:ns:caldav}calendar-description'             => $car->getName(),
                '{urn:ietf:params:xml:ns:caldav}calendar-timezone'                => '',
                '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
                '{urn:ietf:params:xml:ns:caldav}schedule-calendar-transp'         => new ScheduleCalendarTransp('transparent'),
                '{http://apple.com/ns/ical/}calendar-color'                       => '#1baff4',
                'ctag'                                                             => $ctag,
            ];
        }

        return $calendars;
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new Forbidden('Creating calendars is not supported.');
    }

    public function deleteCalendar($calendarId)
    {
        throw new Forbidden('Deleting calendars is not supported.');
    }

    // -------------------------------------------------------------------------
    // Calendar object methods
    // -------------------------------------------------------------------------

    public function getCalendarObjects($calendarId)
    {
        $bookings = $this->bookingRepository->findBy(['car' => $calendarId], ['startDate' => 'ASC']);

        return array_map(fn(Booking $b) => $this->bookingToObjectInfo($b), $bookings);
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        $id = $this->parseBookingId($objectUri);
        if (!$id) {
            return false;
        }

        $booking = $this->bookingRepository->find($id);
        if (!$booking || $booking->getCar()->getId() !== (int) $calendarId) {
            return false;
        }

        return $this->bookingToObjectInfo($booking);
    }

    public function getMultipleCalendarObjects($calendarId, array $uris)
    {
        $objects = [];
        foreach ($uris as $uri) {
            $obj = $this->getCalendarObject($calendarId, $uri);
            if ($obj !== false) {
                $objects[] = $obj;
            }
        }

        return $objects;
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $car = $this->carRepository->find($calendarId);
        if (!$car) {
            throw new NotFound('Car not found.');
        }

        $currentUser = $this->authBackend->getCurrentUser();
        if (!$currentUser || !$car->hasUser($currentUser)) {
            throw new Forbidden('You are not a member of this car.');
        }

        $vcalendar = Reader::read($calendarData);
        $vevent = $vcalendar->VEVENT;

        $booking = new Booking();
        $booking->setCar($car);
        $booking->setUser($currentUser);
        $booking->setEditor($currentUser);
        $booking->setStatus('fixed');

        $this->applyVEventToBooking($booking, $vevent);

        $this->em->persist($booking);
        $this->em->flush();

        return null;
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $id = $this->parseBookingId($objectUri);
        $booking = $id ? $this->bookingRepository->find($id) : null;

        if (!$booking || $booking->getCar()->getId() !== (int) $calendarId) {
            throw new NotFound('Booking not found.');
        }

        $currentUser = $this->authBackend->getCurrentUser();
        if (!$currentUser || !$booking->getCar()->hasUser($currentUser)) {
            throw new Forbidden('You are not a member of this car.');
        }

        $vcalendar = Reader::read($calendarData);
        $this->applyVEventToBooking($booking, $vcalendar->VEVENT);

        $this->em->flush();

        return null;
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $id = $this->parseBookingId($objectUri);
        $booking = $id ? $this->bookingRepository->find($id) : null;

        if (!$booking || $booking->getCar()->getId() !== (int) $calendarId) {
            throw new NotFound('Booking not found.');
        }

        $currentUser = $this->authBackend->getCurrentUser();
        if (!$currentUser || !$booking->getCar()->hasUser($currentUser)) {
            throw new Forbidden('You are not a member of this car.');
        }

        $this->em->remove($booking);
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function bookingToObjectInfo(Booking $booking): array
    {
        $ical = $this->bookingToIcal($booking);

        return [
            'id'           => $booking->getId(),
            'uri'          => 'booking-' . $booking->getId() . '.ics',
            'lastmodified' => null,
            'etag'         => '"' . md5($ical) . '"',
            'calendarid'   => $booking->getCar()->getId(),
            'size'         => strlen($ical),
            'calendardata' => $ical,
            'component'    => 'vevent',
        ];
    }

    private function bookingToIcal(Booking $booking): string
    {
        $vcalendar = new VCalendar();
        $vevent = $vcalendar->add('VEVENT');

        $vevent->add('UID', 'booking-' . $booking->getId() . '@car-coop');
        $vevent->add('SUMMARY', $booking->getTitle() ?? '(' . $booking->getUser()?->getName() . ')');
        $vevent->add('STATUS', $booking->getStatus() === 'maybe' ? 'TENTATIVE' : 'CONFIRMED');

        // All-day events: DATE format; DTEND is exclusive so +1 day
        $start = \DateTime::createFromInterface($booking->getStartDate());
        $end   = \DateTime::createFromInterface($booking->getEndDate());

        $vevent->add('DTSTART', $start, ['VALUE' => 'DATE']);
        $vevent->add('DTEND', (clone $end)->modify('+1 day'), ['VALUE' => 'DATE']);

        if ($booking->getUser()) {
            $vevent->add('ORGANIZER', 'mailto:' . $booking->getUser()->getEmail());
        }

        return $vcalendar->serialize();
    }

    private function applyVEventToBooking(Booking $booking, \Sabre\VObject\Component\VEvent $vevent): void
    {
        if (isset($vevent->SUMMARY)) {
            $booking->setTitle((string) $vevent->SUMMARY);
        }

        if (isset($vevent->DTSTART)) {
            $dt = $vevent->DTSTART->getDateTime();
            $booking->setStartDate(\DateTimeImmutable::createFromMutable($dt));
        }

        if (isset($vevent->DTEND)) {
            $dt = $vevent->DTEND->getDateTime();
            $end = \DateTimeImmutable::createFromMutable($dt);
            // All-day events: DTEND is exclusive, subtract 1 day to get inclusive end
            if (!isset($vevent->DTSTART['TZID']) && $vevent->DTSTART->isFloating()) {
                $end = $end->modify('-1 day');
            }
            $booking->setEndDate($end);
        }

        if (isset($vevent->STATUS)) {
            $icalStatus = strtolower((string) $vevent->STATUS);
            $booking->setStatus($icalStatus === 'tentative' ? 'maybe' : 'fixed');
        }
    }

    private function computeCtag(int $carId): string
    {
        $bookings = $this->bookingRepository->findBy(['car' => $carId]);
        $ids = array_map(fn(Booking $b) => $b->getId(), $bookings);
        sort($ids);

        return md5($carId . '-' . implode(',', $ids) . '-' . count($ids));
    }

    private function parseBookingId(string $objectUri): ?int
    {
        if (preg_match('/^booking-(\d+)\.ics$/', $objectUri, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function getUserFromPrincipal(string $principalUri): ?\App\Entity\User
    {
        if (!str_starts_with($principalUri, 'principals/')) {
            return null;
        }

        $email = substr($principalUri, strlen('principals/'));

        return $this->userRepository->findOneBy(['email' => $email]);
    }
}
