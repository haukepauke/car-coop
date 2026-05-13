<?php

namespace App\Tests\Scenario;

use App\Entity\Car;
use App\Entity\CarHandbook;
use App\Entity\User;
use App\Entity\UserType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CarHandbookScenarioTest extends ScenarioTestCase
{
    public function testMemberCanCreateHandbookAndShowPageButtonSwitchesLabel(): void
    {
        $user = $this->createUser('handbook-member@test.local', locale: 'en');
        [$car] = $this->createCarMembership($user, 'Handbook Car');

        $this->loginThroughForm('handbook-member@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $this->client->request('GET', '/admin/car/show');
        self::assertStringContainsString('Create handbook', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('# Quick overview', (string) $this->client->getResponse()->getContent());

        $crawler = $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook/edit');
        $form = $crawler->filterXPath('//form')->form([
            'car_handbook_form[content]' => "# Key handover\n\n- The key lives in the glove box.\n- Please leave at least a quarter tank.",
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/car/' . $car->getId() . '/handbook');
        $this->client->followRedirect();

        self::assertStringContainsString('Key handover', (string) $this->client->getResponse()->getContent());

        $handbook = $this->em()->getRepository(CarHandbook::class)->findOneBy(['car' => $car]);
        self::assertInstanceOf(CarHandbook::class, $handbook);

        $this->client->request('GET', '/admin/car/show');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Handbook', $content);
        self::assertStringNotContainsString('Create handbook', $content);
    }

    public function testFirstTimeHandbookExampleUsesUserLanguage(): void
    {
        $user = $this->createUser('handbook-de@test.local', locale: 'de');
        [$car] = $this->createCarMembership($user, 'Deutsches Auto');

        $this->loginThroughForm('handbook-de@test.local', 'ScenarioPass123!', 'de');
        $this->followRedirectChain();

        $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('# Kurzueberblick', (string) $this->client->getResponse()->getContent());
    }

    public function testUploadedPhotoPlaceholderIsResolvedToAttachmentMarkdown(): void
    {
        $user = $this->createUser('handbook-photo@test.local', locale: 'en');
        [$car] = $this->createCarMembership($user, 'Photo Handbook Car');

        $this->loginThroughForm('handbook-photo@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook/edit');
        $formValues = $crawler->filterXPath('//form')->form()->getPhpValues();
        $formToken = $formValues['car_handbook_form']['_token'] ?? null;

        self::assertIsString($formToken);

        $imagePath = tempnam(sys_get_temp_dir(), 'handbook-photo-');
        self::assertIsString($imagePath);
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl7s2QAAAAASUVORK5CYII=', true));

        $uploadedFile = new UploadedFile($imagePath, 'email-logo.png', 'image/png', null, true);

        try {
            $this->client->request('POST', '/admin/car/' . $car->getId() . '/handbook/edit', [
                'car_handbook_form' => [
                    'content' => "# Photos\n\n![Default Car](handbook-upload://photo-1)",
                    '_token' => $formToken,
                ],
                'photoTokens' => ['photo-1'],
            ], [
                'car_handbook_form' => [
                    'photos' => [$uploadedFile],
                ],
            ]);
        } finally {
            @unlink($imagePath);
        }

        self::assertResponseRedirects('/admin/car/' . $car->getId() . '/handbook');
        $this->client->followRedirect();
        self::assertStringContainsString('<img', (string) $this->client->getResponse()->getContent());

        $handbook = $this->em()->getRepository(CarHandbook::class)->findOneBy(['car' => $car]);
        self::assertInstanceOf(CarHandbook::class, $handbook);
        self::assertStringNotContainsString('handbook-upload://photo-1', $handbook->getContent());
        self::assertStringContainsString('/admin/car/' . $car->getId() . '/handbook/attachments/', $handbook->getContent());
        self::assertCount(1, $handbook->getPhotos());
    }

    public function testOccasionalUserCanViewButCannotCreateEditOrDeleteHandbook(): void
    {
        $owner = $this->createUser('handbook-owner@test.local', locale: 'en');
        [$car] = $this->createCarMembership($owner, 'Shared Handbook Car');

        $guest = $this->createUser('handbook-guest@test.local', locale: 'en');
        $guestGroup = new UserType();
        $guestGroup->setCar($car);
        $guestGroup->setName('Guests');
        $guestGroup->setPricePerUnit(0.5);
        $guestGroup->setAdmin(false);
        $guestGroup->setFixed(false);
        $guestGroup->setActive(true);
        $guestGroup->setOccasionalUse(true);
        $guestGroup->addUser($guest);
        $this->em()->persist($guestGroup);

        $handbook = new CarHandbook();
        $handbook->setCar($car);
        $handbook->setContent("# House rules\n\nReturn the car clean.");
        $this->em()->persist($handbook);
        $this->em()->flush();

        $this->loginThroughForm('handbook-guest@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('House rules', $content);
        self::assertStringNotContainsString('Edit handbook', $content);

        $this->client->request('GET', '/admin/car/' . $car->getId() . '/handbook/edit');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/car/' . $car->getId() . '/handbook/delete', [
            '_token' => 'invalid',
        ]);
        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em()->getRepository(CarHandbook::class)->findOneBy(['car' => $car]);
        self::assertInstanceOf(CarHandbook::class, $reloaded);
    }
}
