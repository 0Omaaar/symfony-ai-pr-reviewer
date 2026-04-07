<?php

namespace App\Entity;

use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformSettingRepository::class)]
#[ORM\Table(name: 'platform_setting')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLATFORM_SETTING_KEY', fields: ['settingKey'])]
class PlatformSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $settingKey = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settingValue = null;

    #[ORM\Column(length: 20)]
    private string $settingType = 'string';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;

        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): static
    {
        $this->settingValue = $settingValue;

        return $this;
    }

    public function getSettingType(): string
    {
        return $this->settingType;
    }

    public function setSettingType(string $settingType): static
    {
        $this->settingType = $settingType;

        return $this;
    }

    public function getTypedValue(): mixed
    {
        return match ($this->settingType) {
            'bool' => $this->settingValue === '1' || $this->settingValue === 'true',
            'int' => (int) $this->settingValue,
            default => $this->settingValue,
        };
    }
}
