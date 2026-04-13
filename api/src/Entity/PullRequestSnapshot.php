<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PullRequestSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PullRequestSnapshotRepository::class)]
#[ORM\Table(name: 'pull_request_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_user_repo_pr', fields: ['appUser', 'repoFullName', 'prNumber'])]
#[ORM\HasLifecycleCallbacks]
class PullRequestSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $appUser = null;

    #[ORM\Column(length: 100)]
    private ?string $installationId = null;

    #[ORM\Column(length: 255)]
    private ?string $repoFullName = null;

    #[ORM\Column(length: 100)]
    private ?string $repoId = null;

    #[ORM\Column]
    private ?int $prNumber = null;

    #[ORM\Column(length: 100)]
    private ?string $prId = null;

    #[ORM\Column(length: 512)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $authorLogin = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $authorAvatarUrl = null;

    #[ORM\Column(length: 255)]
    private ?string $sourceBranch = null;

    #[ORM\Column(length: 255)]
    private ?string $targetBranch = null;

    #[ORM\Column(length: 20)]
    private string $status = 'open';

    #[ORM\Column(length: 30)]
    private string $reviewStatus = 'none';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ciStatus = null;

    #[ORM\Column]
    private int $commentCount = 0;

    #[ORM\Column]
    private int $changedFiles = 0;

    #[ORM\Column]
    private int $additions = 0;

    #[ORM\Column]
    private int $deletions = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $assignedReviewers = [];

    #[ORM\Column(type: Types::JSON)]
    private array $completedReviews = [];

    #[ORM\Column(type: Types::JSON)]
    private array $labels = [];

    #[ORM\Column(length: 20)]
    private string $aiReviewStatus = 'none';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiReviewSummary = null;

    #[ORM\Column]
    private int $aiIssueCount = 0;

    #[ORM\Column(length: 512)]
    private ?string $githubUrl = null;

    #[ORM\Column]
    private bool $isDraft = false;

    #[ORM\Column]
    private bool $isStale = false;

    #[ORM\Column]
    private int $stalenessThresholdDays = 7;

    #[ORM\Column]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $snapshotUpdatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppUser(): ?User
    {
        return $this->appUser;
    }

    public function setAppUser(User $appUser): static
    {
        $this->appUser = $appUser;
        return $this;
    }

    public function getInstallationId(): ?string
    {
        return $this->installationId;
    }

    public function setInstallationId(string $installationId): static
    {
        $this->installationId = $installationId;
        return $this;
    }

    public function getRepoFullName(): ?string
    {
        return $this->repoFullName;
    }

    public function setRepoFullName(string $repoFullName): static
    {
        $this->repoFullName = $repoFullName;
        return $this;
    }

    public function getRepoId(): ?string
    {
        return $this->repoId;
    }

    public function setRepoId(string $repoId): static
    {
        $this->repoId = $repoId;
        return $this;
    }

    public function getPrNumber(): ?int
    {
        return $this->prNumber;
    }

    public function setPrNumber(int $prNumber): static
    {
        $this->prNumber = $prNumber;
        return $this;
    }

    public function getPrId(): ?string
    {
        return $this->prId;
    }

    public function setPrId(string $prId): static
    {
        $this->prId = $prId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAuthorLogin(): ?string
    {
        return $this->authorLogin;
    }

    public function setAuthorLogin(string $authorLogin): static
    {
        $this->authorLogin = $authorLogin;
        return $this;
    }

    public function getAuthorAvatarUrl(): ?string
    {
        return $this->authorAvatarUrl;
    }

    public function setAuthorAvatarUrl(?string $authorAvatarUrl): static
    {
        $this->authorAvatarUrl = $authorAvatarUrl;
        return $this;
    }

    public function getSourceBranch(): ?string
    {
        return $this->sourceBranch;
    }

    public function setSourceBranch(string $sourceBranch): static
    {
        $this->sourceBranch = $sourceBranch;
        return $this;
    }

    public function getTargetBranch(): ?string
    {
        return $this->targetBranch;
    }

    public function setTargetBranch(string $targetBranch): static
    {
        $this->targetBranch = $targetBranch;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReviewStatus(): string
    {
        return $this->reviewStatus;
    }

    public function setReviewStatus(string $reviewStatus): static
    {
        $this->reviewStatus = $reviewStatus;
        return $this;
    }

    public function getCiStatus(): ?string
    {
        return $this->ciStatus;
    }

    public function setCiStatus(?string $ciStatus): static
    {
        $this->ciStatus = $ciStatus;
        return $this;
    }

    public function getCommentCount(): int
    {
        return $this->commentCount;
    }

    public function setCommentCount(int $commentCount): static
    {
        $this->commentCount = $commentCount;
        return $this;
    }

    public function getChangedFiles(): int
    {
        return $this->changedFiles;
    }

    public function setChangedFiles(int $changedFiles): static
    {
        $this->changedFiles = $changedFiles;
        return $this;
    }

    public function getAdditions(): int
    {
        return $this->additions;
    }

    public function setAdditions(int $additions): static
    {
        $this->additions = $additions;
        return $this;
    }

    public function getDeletions(): int
    {
        return $this->deletions;
    }

    public function setDeletions(int $deletions): static
    {
        $this->deletions = $deletions;
        return $this;
    }

    public function getAssignedReviewers(): array
    {
        return $this->assignedReviewers;
    }

    public function setAssignedReviewers(array $assignedReviewers): static
    {
        $this->assignedReviewers = $assignedReviewers;
        return $this;
    }

    public function getCompletedReviews(): array
    {
        return $this->completedReviews;
    }

    public function setCompletedReviews(array $completedReviews): static
    {
        $this->completedReviews = $completedReviews;
        return $this;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function setLabels(array $labels): static
    {
        $this->labels = $labels;
        return $this;
    }

    public function getAiReviewStatus(): string
    {
        return $this->aiReviewStatus;
    }

    public function setAiReviewStatus(string $aiReviewStatus): static
    {
        $this->aiReviewStatus = $aiReviewStatus;
        return $this;
    }

    public function getAiReviewSummary(): ?string
    {
        return $this->aiReviewSummary;
    }

    public function setAiReviewSummary(?string $aiReviewSummary): static
    {
        $this->aiReviewSummary = $aiReviewSummary;
        return $this;
    }

    public function getAiIssueCount(): int
    {
        return $this->aiIssueCount;
    }

    public function setAiIssueCount(int $aiIssueCount): static
    {
        $this->aiIssueCount = $aiIssueCount;
        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->githubUrl;
    }

    public function setGithubUrl(string $githubUrl): static
    {
        $this->githubUrl = $githubUrl;
        return $this;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function setIsDraft(bool $isDraft): static
    {
        $this->isDraft = $isDraft;
        return $this;
    }

    public function isStale(): bool
    {
        return $this->isStale;
    }

    public function setIsStale(bool $isStale): static
    {
        $this->isStale = $isStale;
        return $this;
    }

    public function getStalenessThresholdDays(): int
    {
        return $this->stalenessThresholdDays;
    }

    public function setStalenessThresholdDays(int $stalenessThresholdDays): static
    {
        $this->stalenessThresholdDays = $stalenessThresholdDays;
        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;
        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    public function getSnapshotUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->snapshotUpdatedAt;
    }

    public function setSnapshotUpdatedAt(\DateTimeImmutable $snapshotUpdatedAt): static
    {
        $this->snapshotUpdatedAt = $snapshotUpdatedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->snapshotUpdatedAt ??= $now;
    }
}
