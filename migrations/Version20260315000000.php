<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parking_location table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parking_location (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, user_id INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_parking_car (car_id), INDEX IDX_parking_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parking_location ADD CONSTRAINT FK_parking_car FOREIGN KEY (car_id) REFERENCES car (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_location ADD CONSTRAINT FK_parking_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_location DROP FOREIGN KEY FK_parking_car');
        $this->addSql('ALTER TABLE parking_location DROP FOREIGN KEY FK_parking_user');
        $this->addSql('DROP TABLE parking_location');
    }
}
