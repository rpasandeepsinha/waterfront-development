<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Jobs;

use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting as TerminateSitebuilderHostingEvent;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateSitebuilderHosting extends AbstractQueueableJob
{
    public function __construct(
        private readonly TerminateSitebuilderHostingEvent $event,
    ) {
        parent::__construct();
    }

    /**
     * @throws ServerNotFoundException
     */
    public function handle(SitebuilderService $sitebuilderService): void
    {
        $sitebuilderService->terminate($this->event->getSubscription());
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
