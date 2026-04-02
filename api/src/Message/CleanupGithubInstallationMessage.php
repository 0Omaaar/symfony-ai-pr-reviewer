<?php

namespace App\Message;

final readonly class CleanupGithubInstallationMessage
{
    public function __construct(
        public int $installationId,
        public string $action,
        public string $deliveryId,
    ) {
    }
}
