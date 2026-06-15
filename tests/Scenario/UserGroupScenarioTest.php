<?php

namespace App\Tests\Scenario;

use App\Entity\UserType;

final class UserGroupScenarioTest extends ScenarioTestCase
{
    public function testNewGroupIsCreatedForActiveCarWhenUserHasMultipleCars(): void
    {
        $user = $this->createUser('multi-car-groups@test.local', name: 'Multi Car User');
        [$firstCar] = $this->createCarMembership($user, 'First Car');
        [$secondCar] = $this->createCarMembership($user, 'Second Car', 'Crew 2');

        $this->loginThroughForm('multi-car-groups@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $this->client->request('POST', '/admin/car/activate/' . $secondCar->getId());
        self::assertResponseStatusCodeSame(204);

        $crawler = $this->client->request('GET', '/admin/usergroup/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filterXPath('//form')->form([
            'user_type_form[name]' => 'Second Car Occasional',
            'user_type_form[pricePerUnit]' => '0.35',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/user/list?car=' . $secondCar->getId());

        /** @var UserType|null $createdGroup */
        $createdGroup = $this->em()->getRepository(UserType::class)->findOneBy(['name' => 'Second Car Occasional']);
        self::assertInstanceOf(UserType::class, $createdGroup);
        self::assertSame($secondCar->getId(), $createdGroup->getCar()?->getId());
        self::assertNotSame($firstCar->getId(), $createdGroup->getCar()?->getId());
    }
}
