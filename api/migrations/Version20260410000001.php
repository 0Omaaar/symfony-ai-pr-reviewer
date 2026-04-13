<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pull_request_snapshot table and onboarding_state JSON column to user';
    }

    public function up(Schema $schema): void
    {
        // Pull request snapshot table
        $this->addSql(<<<'SQL'
            CREATE TABLE pull_request_snapshot (
                id SERIAL PRIMARY KEY,
                app_user_id INTEGER NOT NULL,
                installation_id VARCHAR(100) NOT NULL,
                repo_full_name VARCHAR(255) NOT NULL,
                repo_id VARCHAR(100) NOT NULL,
                pr_number INTEGER NOT NULL,
                pr_id VARCHAR(100) NOT NULL,
                title VARCHAR(512) NOT NULL,
                description TEXT DEFAULT NULL,
                author_login VARCHAR(100) NOT NULL,
                author_avatar_url VARCHAR(512) DEFAULT NULL,
                source_branch VARCHAR(255) NOT NULL,
                target_branch VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                review_status VARCHAR(30) NOT NULL DEFAULT 'none',
                ci_status VARCHAR(20) DEFAULT NULL,
                comment_count INTEGER NOT NULL DEFAULT 0,
                changed_files INTEGER NOT NULL DEFAULT 0,
                additions INTEGER NOT NULL DEFAULT 0,
                deletions INTEGER NOT NULL DEFAULT 0,
                assigned_reviewers JSON NOT NULL DEFAULT '[]',
                completed_reviews JSON NOT NULL DEFAULT '[]',
                labels JSON NOT NULL DEFAULT '[]',
                ai_review_status VARCHAR(20) NOT NULL DEFAULT 'none',
                ai_review_summary TEXT DEFAULT NULL,
                ai_issue_count INTEGER NOT NULL DEFAULT 0,
                github_url VARCHAR(512) NOT NULL,
                is_draft BOOLEAN NOT NULL DEFAULT FALSE,
                is_stale BOOLEAN NOT NULL DEFAULT FALSE,
                staleness_threshold_days INTEGER NOT NULL DEFAULT 7,
                opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                snapshot_updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_pr_snapshot_user FOREIGN KEY (app_user_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_repo_pr ON pull_request_snapshot (app_user_id, repo_full_name, pr_number)');
        $this->addSql('CREATE INDEX idx_pr_snapshot_user_status ON pull_request_snapshot (app_user_id, status)');
        $this->addSql('CREATE INDEX idx_pr_snapshot_repo ON pull_request_snapshot (repo_full_name, status)');
        $this->addSql('CREATE INDEX idx_pr_snapshot_stale ON pull_request_snapshot (is_stale, status)');

        $this->addSql("COMMENT ON COLUMN pull_request_snapshot.opened_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pull_request_snapshot.last_activity_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pull_request_snapshot.snapshot_updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pull_request_snapshot.created_at IS '(DC2Type:datetime_immutable)'");

        // Onboarding state on user
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD onboarding_state JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pull_request_snapshot');
        $this->addSql('ALTER TABLE "user" DROP COLUMN onboarding_state');
    }
}
