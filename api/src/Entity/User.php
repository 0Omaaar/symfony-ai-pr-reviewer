<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $githubId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $githubUsername = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $githubAccessToken = null;

    #[ORM\Column]
    private bool $emailNotificationsEnabled = true;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $unsubscribeToken = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $notificationPreferences = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $onboardingState = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    /**
     * @var Collection<int, UserGithubInstallation>
     */
    #[ORM\OneToMany(targetEntity: UserGithubInstallation::class, mappedBy: 'appUser', orphanRemoval: true)]
    private Collection $githubInstallations;

    public function __construct()
    {
        $this->githubInstallations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getGithubId(): ?string
    {
        return $this->githubId;
    }

    public function setGithubId(?string $githubId): static
    {
        $this->githubId = $githubId;

        return $this;
    }

    public function getGithubUsername(): ?string
    {
        return $this->githubUsername;
    }

    public function setGithubUsername(?string $githubUsername): static
    {
        $this->githubUsername = $githubUsername;

        return $this;
    }

    public function getGithubAccessToken(): ?string
    {
        return $this->githubAccessToken;
    }

    public function setGithubAccessToken(?string $githubAccessToken): static
    {
        $this->githubAccessToken = $githubAccessToken;

        return $this;
    }

    public function isEmailNotificationsEnabled(): bool
    {
        return $this->emailNotificationsEnabled;
    }

    public function setEmailNotificationsEnabled(bool $enabled): static
    {
        $this->emailNotificationsEnabled = $enabled;

        return $this;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $token): static
    {
        $this->unsubscribeToken = $token;

        return $this;
    }

    public function getNotificationPreferences(): array
    {
        return \array_replace_recursive(self::defaultNotificationPreferences(), $this->notificationPreferences ?? []);
    }

    public function setNotificationPreferences(array $preferences): static
    {
        $this->notificationPreferences = $preferences;

        return $this;
    }

    public function getOnboardingState(): array
    {
        return \array_replace_recursive(self::defaultOnboardingState(), $this->onboardingState ?? []);
    }

    public function setOnboardingState(array $state): static
    {
        $this->onboardingState = $state;

        return $this;
    }

    public static function defaultOnboardingState(): array
    {
        return [
            'completedSteps' => [],
            'dismissedAt' => null,
            'completedAt' => null,
            'firstReviewReceivedAt' => null,
        ];
    }

    public function completeOnboardingStep(string $step): static
    {
        $state = $this->getOnboardingState();
        if (!\in_array($step, $state['completedSteps'], true)) {
            $state['completedSteps'][] = $step;
        }

        $allSteps = ['github_connected', 'app_installed', 'branch_activated', 'preferences_set', 'first_review_received'];
        if (\count(\array_intersect($allSteps, $state['completedSteps'])) === \count($allSteps) && $state['completedAt'] === null) {
            $state['completedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        }

        $this->onboardingState = $state;

        return $this;
    }

    public function isOnboardingStepComplete(string $step): bool
    {
        return \in_array($step, $this->getOnboardingState()['completedSteps'], true);
    }

    public function isOnboardingDismissed(): bool
    {
        return $this->getOnboardingState()['dismissedAt'] !== null;
    }

    public function isOnboardingComplete(): bool
    {
        return $this->getOnboardingState()['completedAt'] !== null;
    }

    public static function defaultNotificationPreferences(): array
    {
        return [
            'events' => [
                'opened' => true,
                'closed' => true,
                'synchronize' => true,
                'ready_for_review' => true,
                'converted_to_draft' => true,
            ],
            'repos' => [
                'mode' => 'all',
                'allowed' => [],
            ],
        ];
    }

    public function generateUnsubscribeToken(): static
    {
        if ($this->unsubscribeToken === null) {
            $this->unsubscribeToken = bin2hex(random_bytes(32));
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtOnPersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function setSuspendedAt(?\DateTimeImmutable $suspendedAt): static
    {
        $this->suspendedAt = $suspendedAt;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->suspendedAt !== null;
    }

    /**
     * @return Collection<int, UserGithubInstallation>
     */
    public function getGithubInstallations(): Collection
    {
        return $this->githubInstallations;
    }

    public function addGithubInstallation(UserGithubInstallation $githubInstallation): static
    {
        if (!$this->githubInstallations->contains($githubInstallation)) {
            $this->githubInstallations->add($githubInstallation);
            $githubInstallation->setAppUser($this);
        }

        return $this;
    }

    public function removeGithubInstallation(UserGithubInstallation $githubInstallation): static
    {
        $this->githubInstallations->removeElement($githubInstallation);

        return $this;
    }
}
