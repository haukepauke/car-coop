<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set light as the default theme for new users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user MODIFY theme_preference VARCHAR(10) NOT NULL DEFAULT 'light'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user MODIFY theme_preference VARCHAR(10) NOT NULL DEFAULT 'classic'");
    }
}
