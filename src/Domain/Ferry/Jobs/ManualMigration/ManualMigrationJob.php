<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\ManualMigration;

use Throwable;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

/**
 * @see MigrationJobEventListener
 */
abstract class ManualMigrationJob extends AbstractQueueableJob
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function failed(?Throwable $exception): void
    {
        // To prevent model events from running twice
        if ($this->subscription->technical_status !== DomainStatus::FAILED->value) {
            $this->subscription->technical_status = DomainStatus::FAILED->value;
            $this->subscription->save();
        }
    }

    abstract public function getMigrationStep(): MigrationStep;

    protected function getQueueName(): QueueName
    {
        return QueueName::MIGRATIONS;
    }
}
