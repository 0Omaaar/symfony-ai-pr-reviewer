<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Entity\PlatformSetting;
use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/settings')]
class AdminSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlatformSettingRepository $settingRepository,
    ) {
    }

    #[Route('', name: 'admin_settings_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $settings = $this->settingRepository->getAllAsMap();

        return $this->json([
            'settings' => $settings,
            'available_keys' => [
                'maintenance_mode' => ['type' => 'bool', 'description' => 'Put the platform in maintenance mode (users cannot access)'],
                'disable_new_signups' => ['type' => 'bool', 'description' => 'Prevent new users from signing up'],
                'default_email_notifications' => ['type' => 'bool', 'description' => 'Default email notification setting for new users'],
            ],
        ]);
    }

    #[Route('', name: 'admin_settings_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Bad Request', 'message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $allowed = ['maintenance_mode', 'disable_new_signups', 'default_email_notifications'];
        $updated = [];

        foreach ($data as $key => $value) {
            if (!\in_array($key, $allowed, true)) {
                continue;
            }

            $setting = $this->settingRepository->findByKey($key);
            if ($setting === null) {
                $setting = new PlatformSetting();
                $setting->setSettingKey($key);
                $setting->setSettingType('bool');
            }

            $setting->setSettingValue($value ? '1' : '0');
            $this->em->persist($setting);
            $updated[$key] = $value;
        }

        if ($updated !== []) {
            $log = new AdminLog();
            $log->setAction('settings_updated');
            $log->setMetadata(['updated_keys' => array_keys($updated), 'values' => $updated]);
            $this->em->persist($log);
            $this->em->flush();
        }

        return $this->json(['updated' => $updated]);
    }

    #[Route('/danger/clear-webhook-events', name: 'admin_settings_clear_webhooks', methods: ['DELETE'])]
    public function clearWebhookEvents(): JsonResponse
    {
        $conn = $this->em->getConnection();
        $deleted = $conn->executeStatement('DELETE FROM processed_webhook_delivery');

        $log = new AdminLog();
        $log->setAction('webhook_events_cleared');
        $log->setMetadata(['deleted_count' => $deleted]);
        $this->em->persist($log);
        $this->em->flush();

        return $this->json(['deleted' => $deleted]);
    }
}
