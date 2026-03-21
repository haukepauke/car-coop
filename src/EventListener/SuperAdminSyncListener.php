<?php

namespace App\EventListener;

use App\Service\SuperAdminSyncService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SuperAdminSyncListener implements EventSubscriberInterface
{
    private const CACHE_KEY = 'superadmin.env_hash';

    public function __construct(
        private readonly SuperAdminSyncService $syncService,
        private readonly CacheInterface $cache,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $currentHash = $this->syncService->getCurrentHash();

        $cachedHash = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(null); // persist until explicitly deleted
            return null;
        });

        if ($cachedHash === $currentHash) {
            return;
        }

        $this->syncService->sync();

        // Update cached hash to the new value
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($currentHash) {
            $item->expiresAfter(null);
            return $currentHash;
        });
    }
}
