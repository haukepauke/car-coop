<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add one markdown handbook per car with tracked photo attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE car_handbook (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, content LONGTEXT NOT NULL, photos JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_A24D44F7C3C6F69F (car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE car_handbook ADD CONSTRAINT FK_A24D44F7C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE car_handbook');
    }
}
