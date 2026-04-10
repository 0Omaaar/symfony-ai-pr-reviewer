<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sessions table, github_access_token to user, and repo_subscription table';
    }

    public function up(Schema $schema): void
    {
        // Database-backed sessions (survives container restarts)
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
                sess_id VARCHAR(128) NOT NULL,
                sess_data BYTEA NOT NULL,
                sess_lifetime INTEGER NOT NULL,
                sess_time INTEGER NOT NULL,
                PRIMARY KEY (sess_id)
            )
        SQL);
        $this->addSql('CREATE INDEX sessions_sess_lifetime_idx ON sessions (sess_lifetime)');

        // Persist GitHub OAuth token on user
        $this->addSql('ALTER TABLE "user" ADD github_access_token VARCHAR(255) DEFAULT NULL');

        // Repo + branch subscription system
        $this->addSql(<<<'SQL'
            CREATE TABLE repo_subscription (
                id SERIAL PRIMARY KEY,
                app_user_id INTEGER NOT NULL,
                installation_id VARCHAR(100) NOT NULL,
                repo_full_name VARCHAR(255) NOT NULL,
                repo_id VARCHAR(100) NOT NULL,
                branch VARCHAR(255) NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                activated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_repo_sub_user FOREIGN KEY (app_user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_repo_branch ON repo_subscription (app_user_id, repo_full_name, branch)');
        $this->addSql('CREATE INDEX idx_repo_sub_repo_branch ON repo_subscription (repo_full_name, branch, is_active)');
        $this->addSql('CREATE INDEX idx_repo_sub_user_active ON repo_subscription (app_user_id, is_active)');
        $this->addSql('CREATE INDEX idx_repo_sub_installation ON repo_subscription (installation_id, is_active)');

        $this->addSql("COMMENT ON COLUMN repo_subscription.activated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN repo_subscription.deactivated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN repo_subscription.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN repo_subscription.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE repo_subscription');
        $this->addSql('ALTER TABLE "user" DROP COLUMN github_access_token');
        $this->addSql('DROP TABLE sessions');
    }
}
