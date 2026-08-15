<?php

namespace App\Tests\Scenario;

use App\Entity\Invitation;

final class InvitationScenarioTest extends ScenarioTestCase
{
    public function testExistingUserCanAcceptInvitationAfterLoggingIn(): void
    {
        $inviter = $this->createUser('inviter@test.local', name: 'Inviter');
        [, $group] = $this->createCarMembership($inviter, 'Coop Car');

        $inviteeEmail = 'existing-invitee@test.local';
        $inviteePassword = 'ScenarioPass123!';
        $this->createUser($inviteeEmail, $inviteePassword, true, 'Existing Invitee');
        $this->createInvitation($inviter, $group, $inviteeEmail, 'existing-user-invite-hash');

        $this->client->request('GET', '/invite/accept/existing-user-invite-hash');
        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        $this->loginThroughForm($inviteeEmail, $inviteePassword);
        $this->followRedirectChain();

        $this->assertResponseIsSuccessful();
        self::assertSame('/admin/car/show', $this->client->getRequest()->getPathInfo());

        $invitee = $this->reloadUser($inviteeEmail);
        self::assertSame('Coop Car', $invitee->getCar()?->getName());

        $invitation = $this->em()->getRepository(Invitation::class)->findOneBy(['hash' => 'existing-user-invite-hash']);
        self::assertNull($invitation);
    }

    public function testExistingUserRegisterInviteLinkUsesAcceptFlow(): void
    {
        $inviter = $this->createUser('inviter@test.local', name: 'Inviter');
        [, $group] = $this->createCarMembership($inviter, 'Coop Car');

        $inviteeEmail = 'existing-register-invitee@test.local';
        $inviteePassword = 'ScenarioPass123!';
        $this->createUser($inviteeEmail, $inviteePassword, true, 'Existing Register Invitee');
        $this->createInvitation($inviter, $group, $inviteeEmail, 'existing-user-register-invite-hash');

        $this->client->request('GET', '/register/invited/existing-user-register-invite-hash');
        $this->assertResponseRedirects('/invite/accept/existing-user-register-invite-hash');

        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        $this->loginThroughForm($inviteeEmail, $inviteePassword);
        $this->followRedirectChain();

        $this->assertResponseIsSuccessful();
        self::assertSame('/admin/car/show', $this->client->getRequest()->getPathInfo());

        $invitee = $this->reloadUser($inviteeEmail);
        self::assertSame('Coop Car', $invitee->getCar()?->getName());

        $invitation = $this->em()->getRepository(Invitation::class)->findOneBy(['hash' => 'existing-user-register-invite-hash']);
        self::assertNull($invitation);
    }
}
