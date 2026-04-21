<?php

namespace App\Tests\Scenario;

final class UserProfileScenarioTest extends ScenarioTestCase
{
    public function testUserCanChangeThemePreferenceFromProfile(): void
    {
        $user = $this->createUser('theme-scenario@test.local');
        $this->createCarMembership($user);

        $this->loginThroughForm('theme-scenario@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/user/edit');
        $form = $crawler->filterXPath('//form')->form([
            'user_form[themePreference]' => 'dark',
        ]);

        $this->client->submit($form);
        $this->followRedirectChain();

        self::assertSame('dark', $this->reloadUser('theme-scenario@test.local')->getThemePreference());
        self::assertStringContainsString('data-theme="dark"', (string) $this->client->getResponse()->getContent());
    }

    public function testUserCanChangeThemePreferenceToClassicFromProfile(): void
    {
        $user = $this->createUser('classic-theme-scenario@test.local');
        $this->createCarMembership($user);

        $this->loginThroughForm('classic-theme-scenario@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/user/edit');
        $form = $crawler->filterXPath('//form')->form([
            'user_form[themePreference]' => 'classic',
        ]);

        $this->client->submit($form);
        $this->followRedirectChain();

        self::assertSame('classic', $this->reloadUser('classic-theme-scenario@test.local')->getThemePreference());
        self::assertStringContainsString('data-theme="classic"', (string) $this->client->getResponse()->getContent());
    }

    public function testUserCanExplicitlyHideWelcomeTour(): void
    {
        $user = $this->createUser('tour-hide-scenario@test.local');
        $this->createCarMembership($user);

        $this->loginThroughForm('tour-hide-scenario@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/car/show');
        self::assertStringContainsString('id="tour-banner"', (string) $this->client->getResponse()->getContent());

        $config = json_decode($crawler->filterXPath('//*[@id="tour-config"]')->text(), true, 512, JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/admin/user/tour/hide', [
            '_token' => $config['hideToken'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->reloadUser('tour-hide-scenario@test.local')->isShowWelcomeTour());

        $this->client->request('GET', '/admin/car/show');
        self::assertStringNotContainsString('id="tour-banner"', (string) $this->client->getResponse()->getContent());
    }
}
