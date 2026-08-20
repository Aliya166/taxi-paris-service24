<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820125926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservations table and link it to users';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservations (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(30) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, vehicle_type VARCHAR(255) NOT NULL, pricing_mode VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) NOT NULL, pickup_address VARCHAR(255) NOT NULL, dropoff_address VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL, passengers SMALLINT NOT NULL, luggage SMALLINT NOT NULL, distance_km NUMERIC(8, 2) DEFAULT NULL, duration_minutes INT DEFAULT NULL, base_price NUMERIC(10, 2) DEFAULT NULL, discount_percentage SMALLINT DEFAULT 0 NOT NULL, discount_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, final_price NUMERIC(10, 2) DEFAULT NULL, price_is_estimated TINYINT DEFAULT 1 NOT NULL, transport_reference VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, email_marketing_consent TINYINT DEFAULT 0 NOT NULL, sms_marketing_consent TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, cancellation_reason VARCHAR(255) DEFAULT NULL, customer_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4DA239AEA34913 (reference), INDEX IDX_4DA2399395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT FK_4DA2399395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY FK_4DA2399395C3F3');
        $this->addSql('DROP TABLE reservations');
    }
}
