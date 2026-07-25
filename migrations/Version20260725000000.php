<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store historical per-user trip cost shares';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trip ADD cost_shares JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trip DROP cost_shares');
    }
}
