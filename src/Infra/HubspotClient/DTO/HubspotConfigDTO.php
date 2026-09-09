<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\DTO;

use SensitiveParameter;

readonly class HubspotConfigDTO
{
    /**
     * @param int<1, max> $createBatchSize
     * @param int<1, max> $updateBatchSize
     */
    public function __construct(
        #[SensitiveParameter]
        public string $accessToken,
        public string $baseUrl,
        public string $subscriptionObjectTypeId,
        public string $subscriptionContactId,
        public string $marketingMailActions,
        public string $marketingMailSurveys,
        public string $marketingMailNewsletter,
        public int $createBatchSize,
        public int $updateBatchSize,
    ) {
    }
}
