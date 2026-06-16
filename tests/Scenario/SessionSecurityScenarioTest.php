<?php

namespace App\Tests\Scenario;

class SessionSecurityScenarioTest extends ScenarioTestCase
{
    public function testLogoutRequiresPostAndValidCsrfToken(): void
    {
        $user = $this->createUser('logout-security@test.local');
        $this->createCarMembership($user, 'Logout Security Car');

        $this->loginThroughForm('logout-security@test.local', 'ScenarioPass123!');
        $crawler = $this->followRedirectChain();
        self::assertNotNull($crawler);

        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/logout', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/admin/car/show');
        $form = $crawler->filterXPath('//form[@action="/logout"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('https://car-coop.net');

        $this->client->request('GET', '/admin/car/show');
        self::assertResponseRedirects('/en/login');
    }
}
