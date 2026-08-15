<?php

namespace App\Tests\Scenario;

final class CalendarScenarioTest extends ScenarioTestCase
{
    public function testCalendarEventsRequireLogin(): void
    {
        $this->client->request('GET', '/fc-load-events', [
            'start' => '2026-01-01',
            'end' => '2026-01-31',
        ]);

        self::assertResponseRedirects('/en/login');
    }

    public function testCalendarEventsAreEmptyForUserWithoutCar(): void
    {
        $user = $this->createUser('calendar-without-car@test.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/fc-load-events', [
            'start' => '2026-01-01',
            'end' => '2026-01-31',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }
}
