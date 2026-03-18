<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fuelType and fuelConsumption100 columns to car table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car ADD fuel_type VARCHAR(50) DEFAULT NULL, ADD fuel_consumption100 DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car DROP COLUMN fuel_type, DROP COLUMN fuel_consumption100');
    }
}
