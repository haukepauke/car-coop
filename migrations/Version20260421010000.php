<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set classic as the default theme and migrate existing light users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE user SET theme_preference = 'classic' WHERE theme_preference = 'light'");
        $this->addSql("ALTER TABLE user MODIFY theme_preference VARCHAR(10) NOT NULL DEFAULT 'classic'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE user SET theme_preference = 'light' WHERE theme_preference = 'classic'");
        $this->addSql("ALTER TABLE user MODIFY theme_preference VARCHAR(10) NOT NULL DEFAULT 'light'");
    }
}
