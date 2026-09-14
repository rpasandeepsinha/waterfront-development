<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Listeners;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Events\TerminateDnsZoneEvent;
use Waterfront\Domain\DNS\Listeners\DnsTerminationListener;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

#[CoversClass(DnsTerminationListener::class)]
class DnsTerminationListenerTest extends TestCase
{
    private TerminateDnsZoneEvent $terminateDnsZoneEvent;

    private GandiClient&MockObject $mockGandiClient;

    private ProductRepository&MockObject $mockProductRepository;

    private DnsProductSpecRepository&MockObject $mockDnsProductSpecRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->mockGandiClient = self::createMock(GandiClient::class);
        $this->terminateDnsZoneEvent = new TerminateDnsZoneEvent(
            Uuid::uuid4()->toString(),
            'test.com',
            Uuid::uuid4()->toString(),
        );
        $this->mockProductRepository = self::createMock(ProductRepository::class);
        $this->mockDnsProductSpecRepository = self::createMock(DnsProductSpecRepository::class);
    }

    #[test]
    public function zoneShouldTerminate(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with('Terminating dns zone for subscription {subscription.uuid} domain: {domain.name}');

        $mockDnsService = self::createMock(DnsService::class);
        $mockDnsService->expects(self::once())->method('isSlaveZone')->willReturn(false);

        $this->mockProductRepository->expects(self::once())->method('findProductByUuid')->willReturn(new Product());

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(false);

        $mockDnsService->expects(self::never())->method('disablePremiumDns');

        $this->mockGandiClient->expects(self::never())->method('deleteDomain');

        $mockDnsService->expects(self::once())->method('deleteZone');

        $listener = new DnsTerminationListener(
            $logger,
            $mockDnsService,
            $this->mockProductRepository,
            $this->mockDnsProductSpecRepository,
            $this->mockGandiClient,
        );

        $listener->handle($this->terminateDnsZoneEvent);
    }

    #[test]
    public function zoneShouldTerminateForPremiumDns(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with('Terminating dns zone for subscription {subscription.uuid} domain: {domain.name}');

        $mockDnsService = self::createMock(DnsService::class);
        $mockDnsService->expects(self::once())->method('isSlaveZone')->willReturn(false);

        $this->mockProductRepository->expects(self::once())->method('findProductByUuid')->willReturn(new Product());

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $mockDnsService
            ->expects(self::once())
            ->method('disablePremiumDns')
            ->with($this->terminateDnsZoneEvent->domain, false);

        $this->mockGandiClient
            ->expects(self::once())
            ->method('deleteDomain')
            ->with($this->terminateDnsZoneEvent->domain);

        $mockDnsService->expects(self::once())->method('deleteZone');

        $listener = new DnsTerminationListener(
            $logger,
            $mockDnsService,
            $this->mockProductRepository,
            $this->mockDnsProductSpecRepository,
            $this->mockGandiClient,
        );

        $listener->handle($this->terminateDnsZoneEvent);
    }

    #[test]
    public function slaveZoneShouldNotBeDeleted(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('notice')
            ->with(
                'slave zone is trying to be deleted for domain {domain.name} , subscription uuid: {subscription.uuid}',
            );
        $mockDnsService = self::createMock(DnsService::class);
        $mockDnsService->expects(self::once())->method('isSlaveZone')->willReturn(true);

        $this->mockProductRepository->expects(self::never())->method('findProductByUuid');

        $this->mockDnsProductSpecRepository->expects(self::never())->method('isPremiumDns');

        $mockDnsService->expects(self::never())->method('disablePremiumDns');

        $this->mockGandiClient->expects(self::never())->method('deleteDomain');

        $mockDnsService->expects(self::never())->method('deleteZone');

        $listener = new DnsTerminationListener(
            $logger,
            $mockDnsService,
            $this->mockProductRepository,
            $this->mockDnsProductSpecRepository,
            $this->mockGandiClient,
        );

        $listener->handle($this->terminateDnsZoneEvent);
    }

    #[test]
    public function deleteDnsZoneThrowsPdnsResponseException(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Throwable catch: {exception} for domain {domain.name}');
        $mockDnsService = self::createMock(DnsService::class);
        $mockDnsService->expects(self::once())->method('isSlaveZone')->willReturn(false);

        $this->mockProductRepository->expects(self::once())->method('findProductByUuid')->willReturn(new Product());

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(false);

        $mockDnsService->expects(self::never())->method('disablePremiumDns');

        $this->mockGandiClient->expects(self::never())->method('deleteDomain');

        $mockDnsService->expects(self::once())->method('deleteZone')->willThrowException(new PdnsResponseException());

        $listener = new DnsTerminationListener(
            $logger,
            $mockDnsService,
            $this->mockProductRepository,
            $this->mockDnsProductSpecRepository,
            $this->mockGandiClient,
        );

        $listener->handle($this->terminateDnsZoneEvent);
    }
}
