<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace trip.user_id (ManyToOne) with trip_user join table (ManyToMany)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE trip_user (trip_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_41B18EC7A5BC2E0E (trip_id), INDEX IDX_41B18EC7A76ED395 (user_id), PRIMARY KEY(trip_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE trip_user ADD CONSTRAINT FK_41B18EC7A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trip_user ADD CONSTRAINT FK_41B18EC7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO trip_user (trip_id, user_id) SELECT id, user_id FROM trip WHERE user_id IS NOT NULL');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BA76ED395');
        $this->addSql('ALTER TABLE trip DROP COLUMN user_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trip ADD user_id INT DEFAULT NULL');
        $this->addSql('UPDATE trip t INNER JOIN trip_user tu ON tu.trip_id = t.id SET t.user_id = tu.user_id');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trip_user DROP FOREIGN KEY FK_41B18EC7A5BC2E0E');
        $this->addSql('ALTER TABLE trip_user DROP FOREIGN KEY FK_41B18EC7A76ED395');
        $this->addSql('DROP TABLE trip_user');
    }
}
