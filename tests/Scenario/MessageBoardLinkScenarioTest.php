<?php

namespace App\Tests\Scenario;

use App\Entity\Message;

final class MessageBoardLinkScenarioTest extends ScenarioTestCase
{
    public function testPlainUrlsBecomeClickableForRegularAndBroadcastMessages(): void
    {
        $user = $this->createUser('message-links@test.local', locale: 'en');
        [$car] = $this->createCarMembership($user, 'Link Car');

        $regularMessage = new Message();
        $regularMessage->setCar($car);
        $regularMessage->setAuthor($user);
        $regularMessage->setContent('<p>Docs: https://example.com/path?foo=bar.</p>');
        $this->em()->persist($regularMessage);

        $broadcastMessage = new Message();
        $broadcastMessage->setCar($car);
        $broadcastMessage->setAuthor($user);
        $broadcastMessage->setIsBroadcast(true);
        $broadcastMessage->setContent('<p><strong>Notice</strong></p><p>See also www.example.org/help.</p>');
        $this->em()->persist($broadcastMessage);
        $this->em()->flush();

        $this->loginThroughForm('message-links@test.local', 'ScenarioPass123!');
        $this->followRedirectChain();

        $crawler = $this->client->request('GET', '/admin/messages');
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filterXPath("//a[@href='https://example.com/path?foo=bar' and @target='_blank' and contains(@rel, 'noopener')]"));
        self::assertCount(1, $crawler->filterXPath("//a[@href='https://www.example.org/help' and @target='_blank' and contains(@rel, 'noreferrer')]"));
        self::assertStringContainsString('https://example.com/path?foo=bar</a>.', (string) $this->client->getResponse()->getContent());
    }
}
