<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photos JSON column to message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD photos JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP COLUMN photos');
    }
}
