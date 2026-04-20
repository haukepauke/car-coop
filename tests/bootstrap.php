<?php

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

$testDatabase = dirname(__DIR__) . '/var/test.db';
$testDatabaseDir = dirname($testDatabase);

if (!is_dir($testDatabaseDir)) {
    mkdir($testDatabaseDir, 0777, true);
}

if (file_exists($testDatabase)) {
    unlink($testDatabase);
}

// Create (or recreate) the test database schema once before the test suite runs.
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'test', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();

$em         = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$metadata   = $em->getMetadataFactory()->getAllMetadata();
$schemaTool = new SchemaTool($em);

try {
    $schemaTool->dropSchema($metadata);
} catch (\Throwable) {
    // Schema may not exist yet on the first run — that is fine.
}

$schemaTool->createSchema($metadata);
$kernel->shutdown();
