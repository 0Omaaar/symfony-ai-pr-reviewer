<?php

namespace App\Message;

final readonly class ReviewPullRequestMessage
{
    public function __construct(
        public int $installationId,
        public string $repoFullName,
        public int $prNumber,
        public string $headSha,
        public string $deliveryId
    ) {
    }
}
