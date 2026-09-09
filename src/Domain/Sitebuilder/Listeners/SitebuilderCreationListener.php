<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use JsonException;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Support\Enums\QueueName;

class SitebuilderCreationListener implements ShouldQueue
{
    public string $queue = QueueName::HOSTING->value;

    public function __construct(
        private readonly SitebuilderService $sitebuilderService,
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function handle(CreateSitebuilder $event): void
    {
        $this->sitebuilderService->createSite($event->getSubscription());

        $this->eventDispatcher->dispatch(new CreateMailOnlyHosting(
            contactPersonName: $event->getContactPersonName(),
            contactEmail: $event->getContactEmail(),
            subscription: $event->getSubscription()
        ));
    }
}
