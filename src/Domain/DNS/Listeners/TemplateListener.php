<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Waterfront\Domain\DNS\Events\ZoneOutdated;
use Waterfront\Domain\DNS\Services\Templates\TemplateService;
use Waterfront\Support\Enums\QueueName;

class TemplateListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::DNS->value;

    public function __construct(
        private readonly TemplateService $template,
    ) {
    }

    public function handle(ZoneOutdated $event): void
    {
        $template = $event->template;
        $zone = $event->zone;
        $pdnsZone = $event->pdnsZone;

        $this->template->applyTemplateToZone($template, $zone, $pdnsZone);
    }
}
