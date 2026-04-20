<?php

namespace App\Tests\Scenario;

use App\Entity\User;

final class RegistrationScenarioTest extends ScenarioTestCase
{
    public function testUserCanRegisterVerifyAndReturnToFirstCarOnboarding(): void
    {
        $email = 'register-scenario@test.local';
        $password = 'ScenarioPass123!';

        $crawler = $this->client->request('GET', '/en/register');
        $this->ageRegistrationFormSession();

        $form = $crawler->filterXPath('//form')->form([
            'registration_form[email]' => $email,
            'registration_form[name]' => 'Registered Scenario User',
            'registration_form[locale]' => 'en',
            'registration_form[plainPassword]' => $password,
            'registration_form[agreeTerms]' => 1,
            'registration_form[website]' => '',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/register/check-email');

        $user = $this->reloadUser($email);
        self::assertFalse($user->isVerified());

        $this->client->request('GET', $this->verificationPathFor($user));
        $this->assertResponseRedirects('/admin/car/new');

        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        $this->loginThroughForm($email, $password);
        $this->followRedirectChain();

        $this->assertResponseIsSuccessful();
        self::assertSame('/admin/car/new', $this->client->getRequest()->getPathInfo());

        $verifiedUser = $this->reloadUser($email);
        self::assertTrue($verifiedUser->isVerified());
        self::assertNull($verifiedUser->getCar());
    }
}
