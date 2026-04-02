<?php

namespace App\Entity;

use App\Repository\UserGithubInstallationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGithubInstallationRepository::class)]
#[ORM\Table(name: 'user_github_installation')]
#[ORM\UniqueConstraint(name: 'uniq_user_github_installation_pair', fields: ['appUser', 'installation'])]
class UserGithubInstallation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'githubInstallations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $appUser = null;

    #[ORM\ManyToOne(inversedBy: 'userInstallations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GithubInstallation $installation = null;

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

    public function getInstallation(): ?GithubInstallation
    {
        return $this->installation;
    }

    public function setInstallation(GithubInstallation $installation): static
    {
        $this->installation = $installation;

        return $this;
    }
}
