<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\HubSpot;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Waterfront\Domain\Marketing\HubspotSynchronizer;

#[AsCommand(name: 'hubspot:batch-sync-subscriptions')]
#[Description('Batch sync subscriptions')]
#[Signature('hubspot:batch-sync-subscriptions')]
class HubspotBatchSyncSubscriptions extends Command
{
    /**
     * @throws Throwable
     */
    public function handle(HubspotSynchronizer $hubspotSynchronizer): void
    {
        $hubspotSynchronizer->runSync();
    }
}
