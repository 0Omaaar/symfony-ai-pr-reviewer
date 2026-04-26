<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workspace and workspace_repository tables for personal workspace feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE workspace (
                id SERIAL PRIMARY KEY,
                app_user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_workspace_user FOREIGN KEY (app_user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_workspace_user ON workspace (app_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_workspace_name ON workspace (app_user_id, name)');

        $this->addSql(<<<'SQL'
            CREATE TABLE workspace_repository (
                id SERIAL PRIMARY KEY,
                workspace_id INTEGER NOT NULL,
                repo_full_name VARCHAR(255) NOT NULL,
                repo_id VARCHAR(100) NOT NULL,
                installation_id VARCHAR(100) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_workspace_repo_workspace FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_workspace_repository_workspace ON workspace_repository (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_workspace_repo ON workspace_repository (workspace_id, repo_full_name)');

        $this->addSql("COMMENT ON COLUMN workspace.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN workspace.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN workspace_repository.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workspace_repository');
        $this->addSql('DROP TABLE workspace');
    }
}
