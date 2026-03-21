<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add occasional_use column to user_type table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_type ADD occasional_use TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_type DROP COLUMN occasional_use');
    }
}
