<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessPullRequestMessage
{
    public function __construct(
        public string $provider,
        public string $prId,
        public string $title,
        public string $description,
        public string $repoUrl,
        public string $branch,
        public string $authorToken,
    ) {
    }
}
