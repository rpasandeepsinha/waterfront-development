<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions;

use Waterfront\Domain\Sitebuilder\Services\BasekitMigrationService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class MigrateBasekitSubscriptionJob extends AbstractQueueableJob
{
    public function __construct(
        public readonly string $scriptSlug,
        public readonly string $subscriptionUuid
    ) {
        parent::__construct();
    }

    public function handle(BasekitMigrationService $basekitMigrationService): void
    {
        $basekitMigrationService->migrate($this->scriptSlug, $this->subscriptionUuid);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }
}
