<?php

namespace App\EventSubscriber;

use App\Repository\BookingRepository;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class CalendarSubscriber implements EventSubscriberInterface
{
    private BookingRepository $bookingRepository;
    private UrlGeneratorInterface $router;
    private Security $security;

    public function __construct(
        BookingRepository $bookingRepository,
        UrlGeneratorInterface $router,
        Security $security
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->router = $router;
        $this->security = $security;
    }

    public static function getSubscribedEvents()
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $calendar)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $car = $user->getCar();

        $start = $calendar->getStart();
        $end = $calendar->getEnd();
        $filters = $calendar->getFilters();

        $bookings = $this->bookingRepository
            ->createQueryBuilder('booking')
            ->where('booking.startDate BETWEEN :start and :end OR booking.endDate BETWEEN :start and :end')
            ->andWhere('booking.car = :car')
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'))
            ->setParameter('car', $car)
            ->getQuery()
            ->getResult()
        ;

        foreach ($bookings as $booking) {
            $title = $booking->getUser()->getName();
            if ($booking->getTitle()) {
                $title .= ' - '.$booking->getTitle();
            }

            // this create the events with your data (here booking data) to fill calendar
            $bookingEvent = new Event(
                $title,
                $booking->getStartDate(),
                $booking->getEndDate() // If the end date is null or not defined, a all day event is created.
            );

            /*
             * Add custom options to events
             *
             * For more information see: https://fullcalendar.io/docs/event-object
             * and: https://github.com/fullcalendar/fullcalendar/blob/master/src/core/options.ts
             */

            $bookingEvent->setOptions([
                'backgroundColor' => $booking->getUser()->getColor(),
                'borderColor' => $booking->getUser()->getColor(),
            ]);
            $bookingEvent->addOption(
                'url',
                $this->router->generate('app_booking_edit', [
                    'booking' => $booking->getId(),
                ])
            );

            // finally, add the event to the CalendarEvent to fill the calendar
            $calendar->addEvent($bookingEvent);
        }
    }
}
