<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\RemoveStrayParkingDns;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns\RemoveStrayParkingDnsJob;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;

#[CoversClass(RemoveStrayParkingDnsJob::class)]
#[AllowMockObjectsWithoutExpectations]
class RemoveStrayParkingDnsJobTest extends TestCase
{
    private const string DOMAIN = 'example.nl';

    private HostingDeploymentRepository&MockObject $hostingDeploymentRepository;

    private DnsService&MockObject $dnsService;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingDeploymentRepository = self::createMock(HostingDeploymentRepository::class);
        $this->dnsService = self::createMock(DnsService::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }

    public function testDoesNothingWhenDomainHasNoHostingDeployment(): void
    {
        $this->hostingDeploymentRepository->method('hasHostingDeploymentForDomain')->willReturn(false);

        $this->dnsService->expects(self::never())->method('getConflictingParkingRecords');
        $this->dnsService->expects(self::never())->method('removeConflictingParkingRecords');
        $this->logger->expects(self::never())->method('notice');

        $job = new RemoveStrayParkingDnsJob(self::DOMAIN, false);
        $job->handle($this->hostingDeploymentRepository, $this->dnsService, $this->logger);
    }

    public function testDryRunLogsFindingsButDoesNotRemoveAnything(): void
    {
        $this->hostingDeploymentRepository->method('hasHostingDeploymentForDomain')->willReturn(true);
        $this->dnsService
            ->method('getConflictingParkingRecords')
            ->willReturn([new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600)]);

        $this->dnsService->expects(self::never())->method('removeConflictingParkingRecords');
        $this->logger->expects(self::once())->method('notice');

        $job = new RemoveStrayParkingDnsJob(self::DOMAIN, true);
        $job->handle($this->hostingDeploymentRepository, $this->dnsService, $this->logger);
    }

    public function testRealRunLogsAndRemovesConflictingParkingRecords(): void
    {
        $this->hostingDeploymentRepository->method('hasHostingDeploymentForDomain')->willReturn(true);

        $this->dnsService
            ->expects(self::once())
            ->method('removeConflictingParkingRecords')
            ->with(self::DOMAIN)
            ->willReturn([new DefaultRecord('A', self::DOMAIN, '212.204.220.100', 600)]);
        $this->logger->expects(self::once())->method('notice');

        $job = new RemoveStrayParkingDnsJob(self::DOMAIN, false);
        $job->handle($this->hostingDeploymentRepository, $this->dnsService, $this->logger);
    }

    public function testDoesNothingWhenNoConflictingParkingRecordsFound(): void
    {
        $this->hostingDeploymentRepository->method('hasHostingDeploymentForDomain')->willReturn(true);
        $this->dnsService->method('removeConflictingParkingRecords')->willReturn([]);

        $this->logger->expects(self::never())->method('notice');

        $job = new RemoveStrayParkingDnsJob(self::DOMAIN, false);
        $job->handle($this->hostingDeploymentRepository, $this->dnsService, $this->logger);
    }
}
