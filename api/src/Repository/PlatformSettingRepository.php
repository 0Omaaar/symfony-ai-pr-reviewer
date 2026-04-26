<?php

namespace App\Repository;

use App\Entity\PlatformSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSetting>
 */
class PlatformSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSetting::class);
    }

    public function findByKey(string $key): ?PlatformSetting
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    public function getAllAsMap(): array
    {
        $settings = $this->findAll();
        $map = [];
        foreach ($settings as $setting) {
            $map[$setting->getSettingKey()] = $setting->getTypedValue();
        }

        return $map;
    }
}
