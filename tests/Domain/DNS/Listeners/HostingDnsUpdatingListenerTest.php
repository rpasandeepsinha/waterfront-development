<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Listeners\HostingDnsUpdatingListener;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(HostingDnsUpdatingListener::class)]
class HostingDnsUpdatingListenerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test.com';

    private DnsService&MockObject $dnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsService = self::createMock(DnsService::class);
    }

    #[Test]
    public function handleAppliesDiffReplacingParkingRecords(): void
    {
        $diff = new DnsZoneDiff([]);
        $event = new ReplaceParkingAndUpdateDns(self::DOMAIN, $diff);

        $this->dnsService
            ->expects(self::once())
            ->method('applyDiffReplacingParkingRecords')
            ->with(self::DOMAIN, $diff)
            ->willReturn(new DnsZone(new Fqdn(self::DOMAIN)));

        $listener = new HostingDnsUpdatingListener($this->dnsService);
        $listener->handle($event);

        self::assertSame(QueueName::DNS->value, $listener->queue);
    }

    #[Test]
    public function handleCatchesAndFailsOnDnsException(): void
    {
        $event = new ReplaceParkingAndUpdateDns(self::DOMAIN, new DnsZoneDiff([]));

        $this->dnsService
            ->expects(self::once())
            ->method('applyDiffReplacingParkingRecords')
            ->willThrowException(new DnsZoneNotFoundException('zone not found'));

        $listener = new HostingDnsUpdatingListener($this->dnsService);

        // The listener logs the DNS failure and fails the job; the exception must not bubble up.
        $listener->handle($event);

        self::assertSame(QueueName::DNS->value, $listener->queue);
    }
}
