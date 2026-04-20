<?php

namespace App\Tests\Scenario;

use App\Entity\Invitation;

final class InvitationResendScenarioTest extends ScenarioTestCase
{
    public function testPendingInvitationCanBeResentFromUserAdminPage(): void
    {
        $inviter = $this->createUser('inviter@test.local', name: 'Inviter');
        [, $group] = $this->createCarMembership($inviter, 'Coop Car');
        $invitation = $this->createInvitation($inviter, $group, 'new-user@test.local', 'resend-invite-hash');

        $this->client->loginUser($inviter);
        $crawler = $this->client->request('GET', '/admin/user/list');

        self::assertCount(1, $crawler->filterXPath(sprintf('//form[@action="/admin/invite/resend/%d"]', $invitation->getId())));
        $form = $crawler->filterXPath(sprintf('//form[@action="/admin/invite/resend/%d"]', $invitation->getId()))->form();

        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/user/list');
        $this->client->followRedirect();

        self::assertStringContainsString('Invitation resent to new-user@test.local.', (string) $this->client->getResponse()->getContent());

        $expiredInvitation = $this->em()->getRepository(Invitation::class)->findOneBy(['hash' => 'resend-invite-hash']);
        self::assertNotNull($expiredInvitation);
        self::assertSame('expired', $expiredInvitation->getStatus());

        $activeInvitations = $this->em()->getRepository(Invitation::class)->findBy([
            'email' => 'new-user@test.local',
            'status' => 'new',
        ]);
        self::assertCount(1, $activeInvitations);
        self::assertNotSame('resend-invite-hash', $activeInvitations[0]->getHash());
    }

    public function testExpiredInvitationHashCannotBeAcceptedAfterResend(): void
    {
        $inviter = $this->createUser('inviter@test.local', name: 'Inviter');
        [, $group] = $this->createCarMembership($inviter, 'Coop Car');
        $invitation = $this->createInvitation($inviter, $group, 'existing-user@test.local', 'expired-old-hash');
        $existingUser = $this->createUser('existing-user@test.local', name: 'Existing User');

        $this->client->loginUser($inviter);
        $crawler = $this->client->request('GET', '/admin/user/list');
        $form = $crawler->filterXPath(sprintf('//form[@action="/admin/invite/resend/%d"]', $invitation->getId()))->form();
        $this->client->submit($form);
        $this->followRedirectChain();

        $this->client->loginUser($existingUser);
        $this->client->request('GET', '/invite/accept/expired-old-hash');

        $this->assertResponseRedirects('/en/login');
        self::assertNull($this->em()->getRepository(Invitation::class)->findOneByHash('expired-old-hash'));

        $activeInvitations = $this->em()->getRepository(Invitation::class)->findBy([
            'email' => 'existing-user@test.local',
            'status' => 'new',
        ]);
        self::assertCount(1, $activeInvitations);
        self::assertNotSame('expired-old-hash', $activeInvitations[0]->getHash());
    }
}
