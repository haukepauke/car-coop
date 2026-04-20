<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add theme_preference to user profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD theme_preference VARCHAR(10) NOT NULL DEFAULT 'light'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP theme_preference');
    }
}
