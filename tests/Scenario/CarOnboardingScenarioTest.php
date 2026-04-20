<?php

namespace App\Tests\Scenario;

use App\Entity\Car;
use App\Entity\UserType;

final class CarOnboardingScenarioTest extends ScenarioTestCase
{
    public function testVerifiedUserCanCreateFirstCarAndContinueToPricingStep(): void
    {
        $user = $this->createUser('first-car-owner@test.local', name: 'First Car Owner');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/admin/car/new');
        $form = $crawler->filterXPath('//form')->form([
            'car_form[name]' => 'Roadrunner',
            'car_form[licensePlate]' => 'B-CC-123',
            'car_form[mileage]' => 120000,
            'car_form[milageUnit]' => 'km',
            'car_form[currency]' => 'EUR',
            'car_form[make]' => 'Kombi',
            'car_form[vendor]' => 'Coop Autos',
            'car_form[fuelType]' => 'hybrid',
        ]);

        $this->client->submit($form);
        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertStringContainsString('/admin/car/pricing/', (string) $this->client->getResponse()->headers->get('Location'));

        $crawler = $this->client->followRedirect();
        $pricingForm = $crawler->filterXPath('//form')->form([
            'car_pricing_form[fuelPrice]' => 1.899,
            'car_pricing_form[fuelConsumption100]' => 6.5,
        ]);

        $this->client->submit($pricingForm);
        $this->assertResponseRedirects('/admin/user/invite/onboarding');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        self::assertSame('/admin/user/invite/onboarding', $this->client->getRequest()->getPathInfo());

        /** @var Car|null $car */
        $car = $this->em()->getRepository(Car::class)->findOneBy(['name' => 'Roadrunner']);
        self::assertNotNull($car);
        self::assertTrue($car->hasUser($this->reloadUser('first-car-owner@test.local')));

        /** @var UserType|null $crew */
        $crew = $this->em()->getRepository(UserType::class)->findOneBy(['car' => $car, 'name' => 'Crew']);
        self::assertNotNull($crew);
        self::assertSame(0.22, $crew->getPricePerUnit());
    }
}
