<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Actions;

use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Microsoft365\Actions\AddM365TxtValidationAction;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(AddM365TxtValidationAction::class)]
#[AllowMockObjectsWithoutExpectations]
class AddM365TxtValidationActionTest extends TestCase
{
    private const string TENANT_NAME = 'yourhosting.onmicrosoft.com';

    private const string KPN_CUSTOMER_ID = 'CID12345678';

    private const string PRIMARY_DOMAIN = 'yourhosting.nl';

    private AddM365TxtValidationAction $action;

    private Microsoft365Service&MockObject $microsoft365Service;

    private DnsDeploymentRepository&MockObject $dnsDeploymentRepository;

    private DnsService&MockObject $dnsService;

    private LoggerInterface&MockObject $logger;

    private Microsoft365CustomerInfo $customerInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new AddM365TxtValidationAction(
            microsoft365Service: $this->microsoft365Service = self::createMock(Microsoft365Service::class),
            dnsDeploymentRepository: $this->dnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class),
            dnsService: $this->dnsService = self::createMock(DnsService::class),
            logger: $this->logger = self::createMock(LoggerInterface::class)
        );

        $this->customerInfo = new Microsoft365CustomerInfo();
        $this->customerInfo->id = 1337;
        $this->customerInfo->tenant_name = self::TENANT_NAME;
        $this->customerInfo->kpn_customer_id = self::KPN_CUSTOMER_ID;
    }

    #[Test]
    public function addTxtValidationDnsRecord(): void
    {
        $this->microsoft365Service->expects(self::once())
            ->method('getTenantDefaultDomainName')
            ->willReturn(self::PRIMARY_DOMAIN);

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription = new Subscription();
        $dnsDeployment->subscription->domain = self::PRIMARY_DOMAIN;

        $this->dnsDeploymentRepository->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with(self::PRIMARY_DOMAIN)
            ->willReturn($dnsDeployment);

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::PRIMARY_DOMAIN,
                new DefaultRecord(
                    'TXT',
                    'kpnonboardingpac.' . self::PRIMARY_DOMAIN,
                    '12345678',
                    3600
                )
            );

        $this->action->execute($this->customerInfo);
    }

    #[Test]
    public function whenGraphClientThrowsGuzzleException(): void
    {
        $this->microsoft365Service->expects(self::once())
            ->method('getTenantDefaultDomainName')
            ->willThrowException($exception = self::createMock(GuzzleException::class));

        $this->dnsDeploymentRepository->expects(self::never())
            ->method('getDnsDeploymentFromDomain');

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to add DNS record for M365 TXT record validation.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'm365_customer_info_id' => 1337,
                        'm365_tenant' => self::TENANT_NAME,
                        'm365_kpn_customer_id' => '12345678',
                    ],
                ]
            );

        $this->action->execute($this->customerInfo);
    }

    #[Test]
    public function whenNoDnsDeploymentFound(): void
    {
        $this->microsoft365Service->expects(self::once())
            ->method('getTenantDefaultDomainName')
            ->willReturn(self::PRIMARY_DOMAIN);

        $this->dnsDeploymentRepository->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with(self::PRIMARY_DOMAIN)
            ->willReturn(null);

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->action->execute($this->customerInfo);
    }

    #[Test]
    public function dnsZoneNotFoundException(): void
    {
        $this->microsoft365Service->expects(self::once())
            ->method('getTenantDefaultDomainName')
            ->willReturn(self::PRIMARY_DOMAIN);

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription = new Subscription();
        $dnsDeployment->subscription->domain = self::PRIMARY_DOMAIN;

        $this->dnsDeploymentRepository->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with(self::PRIMARY_DOMAIN)
            ->willReturn($dnsDeployment);

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->willThrowException($exception = self::createMock(DnsZoneNotFoundException::class));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to add DNS record for M365 TXT record validation.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'm365_customer_info_id' => 1337,
                        'm365_tenant' => self::TENANT_NAME,
                        'm365_kpn_customer_id' => '12345678',
                    ],
                ]
            );

        $this->action->execute($this->customerInfo);
    }
}
