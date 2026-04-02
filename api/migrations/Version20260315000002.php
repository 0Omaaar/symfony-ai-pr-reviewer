<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_notifications_enabled and unsubscribe_token to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD email_notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE "user" ADD unsubscribe_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_UNSUBSCRIBE_TOKEN ON "user" (unsubscribe_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_UNSUBSCRIBE_TOKEN');
        $this->addSql('ALTER TABLE "user" DROP email_notifications_enabled');
        $this->addSql('ALTER TABLE "user" DROP unsubscribe_token');
    }
}
