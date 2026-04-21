<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist whether the welcome tour banner should be shown for a user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD show_welcome_tour TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP show_welcome_tour');
    }
}
