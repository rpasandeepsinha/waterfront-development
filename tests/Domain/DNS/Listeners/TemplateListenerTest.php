<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Events\ZoneOutdated;
use Waterfront\Domain\DNS\Listeners\TemplateListener;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Services\Templates\TemplateService;

#[CoversClass(TemplateListener::class)]
class TemplateListenerTest extends IntegrationTestCase
{
    #[Test]
    public function ifTemplateServiceIsCalled(): void
    {
        $template = new DnsCustomerTemplate([
            'name' => 'testTemplate',
            'customer_id' => 1,
        ]);

        $event = new ZoneOutdated($template, 'test.mydomain.com');

        $templateService = self::createMock(TemplateService::class);
        $templateService
            ->expects(self::once())
            ->method('applyTemplateToZone')
            ->with($template, 'test.mydomain.com', null);

        $listener = new TemplateListener($templateService);
        $listener->handle($event);
    }
}
