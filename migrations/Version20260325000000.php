<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add currency column to car table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE car ADD currency VARCHAR(3) NOT NULL DEFAULT 'EUR'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car DROP currency');
    }
}
