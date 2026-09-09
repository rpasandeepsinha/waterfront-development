<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\HubSpot;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;

#[AsCommand(name: 'hubspot:prune-events')]
#[Description('Prunes old events from the hubspot_events table')]
#[Signature('hubspot:prune-events')]
class HubspotPruneEvents extends Command
{
    /**
     * @throws Throwable
     */
    public function handle(HubspotEventRepository $repository): void
    {
        $repository->pruneEvents(5);
    }
}
