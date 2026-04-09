<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin panel: createdAt/suspendedAt to user, admin_log table, platform_setting table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD suspended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".suspended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE "user" SET created_at = NOW() WHERE created_at IS NULL');

        $this->addSql('CREATE TABLE admin_log (
            id SERIAL NOT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id VARCHAR(255) DEFAULT NULL,
            performed_by VARCHAR(100) NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN admin_log.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE platform_setting (
            id SERIAL NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            setting_type VARCHAR(20) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PLATFORM_SETTING_KEY ON platform_setting (setting_key)');

        $this->addSql("INSERT INTO platform_setting (setting_key, setting_value, setting_type) VALUES
            ('maintenance_mode', 'false', 'bool'),
            ('disable_new_signups', 'false', 'bool'),
            ('default_email_notifications', 'true', 'bool')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP created_at');
        $this->addSql('ALTER TABLE "user" DROP suspended_at');
        $this->addSql('DROP TABLE admin_log');
        $this->addSql('DROP TABLE platform_setting');
    }
}
