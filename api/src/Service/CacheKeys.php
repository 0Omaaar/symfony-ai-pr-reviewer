<?php

namespace App\Service;

/**
 * Central registry of all cache key patterns used across the application.
 * Every cache read/write/delete goes through these methods so key strings
 * are never duplicated across files.
 */
final class CacheKeys
{
    public static function userRepositories(int $userId): string
    {
        return \sprintf('github_user_repositories.%d', $userId);
    }

    public static function repositoryDetails(int $userId, int $repoId): string
    {
        return \sprintf('github_user_repository_details.%d.%d', $userId, $repoId);
    }

    public static function repositoryBranches(int $userId, int $repoId): string
    {
        return \sprintf('github_user_repository_branches.%d.%d', $userId, $repoId);
    }

    public static function repositoryPullRequests(int $userId, int $repoId): string
    {
        return \sprintf('github_user_repository_pull_requests.%d.%d', $userId, $repoId);
    }

    public static function repositoryInsights(int $userId, int $repoId): string
    {
        return \sprintf('github_user_repository_insights.%d.%d', $userId, $repoId);
    }

    public static function repositoryLatestEvent(int $userId, int $repoId): string
    {
        return \sprintf('github_user_repository_latest_event.%d.%d', $userId, $repoId);
    }

    public static function pullRequestDetails(int $userId, int $pullRequestId): string
    {
        return \sprintf('github_user_pull_request_details.%d.%d', $userId, $pullRequestId);
    }

    public static function pullRequestChanges(int $userId, int $pullRequestId): string
    {
        return \sprintf('github_user_pull_request_changes.%d.%d', $userId, $pullRequestId);
    }

    public static function prIdToRepoIdMap(int $userId): string
    {
        return \sprintf('github_user_pr_id_to_repo_id.%d', $userId);
    }

    public static function dashboardPayload(int $userId): string
    {
        return \sprintf('dashboard.payload.%d', $userId);
    }
}
