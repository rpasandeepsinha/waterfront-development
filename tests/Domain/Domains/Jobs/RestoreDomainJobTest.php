<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Exceptions\RestoreDomainException;
use Waterfront\Domain\Domains\Jobs\RestoreDomainJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(RestoreDomainJob::class)]
class RestoreDomainJobTest extends IntegrationTestCase
{
    private DomainDeployment $domainDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainDeployment = DomainSubscriptionDataProvider::deployment(domainProvider: ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]));
    }

    #[test]
    public function testRestoreDomainJob(): void
    {
        $detailsAutoRenewOff = include __DIR__ . '/data/domain_details_auto_renew_off.php';
        $detailsAutoRenewOn = include __DIR__ . '/data/domain_details_auto_renew_on.php';
        $serializer = DomainSerializerFactory::getSerializer();

        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects(self::exactly(2))
            ->method('fetchDomain')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willReturnOnConsecutiveCalls(
                $serializer->denormalize($detailsAutoRenewOff, DomainDetailsDTO::class),
                $serializer->denormalize($detailsAutoRenewOn, DomainDetailsDTO::class),
            );

        $domainService
            ->expects(self::once())
            ->method('restore')
            ->with($this->domainDeployment->subscription->domain, $this->domainDeployment->provider->slug);

        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, self::createStub(LoggerInterface::class));

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
    }

    #[test]
    public function testRestoreDomainJobAutoRenewOn(): void
    {
        $detailsAutoRenewOn = include __DIR__ . '/data/domain_details_auto_renew_on.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetailsAutoRenewOn = $serializer->denormalize($detailsAutoRenewOn, DomainDetailsDTO::class);

        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willReturn($domainDetailsAutoRenewOn);
        $domainService
            ->expects(self::never())
            ->method('restore')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            );

        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, self::createStub(LoggerInterface::class));

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
    }

    #[test]
    public function testRestoreDomainJobRestoreException(): void
    {
        $exceptionMessage = 'exception: its broken';
        $detailsAutoRenewOff = include __DIR__ . '/data/domain_details_auto_renew_off.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetailsAutoRenewOff = $serializer->denormalize($detailsAutoRenewOff, DomainDetailsDTO::class);

        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willReturn($domainDetailsAutoRenewOff);
        $domainService
            ->expects(self::once())
            ->method('restore')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willThrowException(new RestoreDomainException($exceptionMessage));

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())->method('error');

        $this->expectException(RestoreDomainException::class);
        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, $loggerMock);

        self::assertSame(TechnicalStatus::FAILED->value, $this->domainDeployment->subscription->technical_status);
        self::assertSame(CarbonImmutable::now(), $this->domainDeployment->last_result_received);
        self::assertSame($exceptionMessage, $this->domainDeployment->last_result);
    }

    #[test]
    public function testRestoreDomainJobFetchDomainException(): void
    {
        $exceptionMessage = 'exception: its broken';

        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willThrowException(new FetchDomainException($exceptionMessage));
        $domainService
            ->expects(self::never())
            ->method('restore')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            );

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())->method('error');

        $this->expectException(FetchDomainException::class);
        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, $loggerMock);

        self::assertSame(TechnicalStatus::FAILED->value, $this->domainDeployment->subscription->technical_status);
        self::assertSame(CarbonImmutable::now(), $this->domainDeployment->last_result_received);
        self::assertSame($exceptionMessage, $this->domainDeployment->last_result);
    }

    #[test]
    public function testRestoreDomainJobSecondFetchDomainThrowsException(): void
    {
        $exceptionMessage = 'exception: its broken';

        $detailsAutoRenewOff = include __DIR__ . '/data/domain_details_auto_renew_off.php';

        $matcher = self::exactly(2);
        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects($matcher)
            ->method('fetchDomain')
            ->willReturnCallback(
                function () use ($detailsAutoRenewOff, $matcher, $exceptionMessage) {
                    if ($matcher->numberOfInvocations() === 1) {
                        $serializer = DomainSerializerFactory::getSerializer();

                        return $serializer->denormalize($detailsAutoRenewOff, DomainDetailsDTO::class);
                    }

                    if ($matcher->numberOfInvocations() === 2) {
                        throw new FetchDomainException($exceptionMessage);
                    }

                    throw new LogicException();
                },
            );
        $domainService
            ->expects(self::once())
            ->method('restore')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            );

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())->method('error');

        $this->expectException(FetchDomainException::class);
        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, $loggerMock);

        self::assertSame(TechnicalStatus::FAILED->value, $this->domainDeployment->subscription->technical_status);
        self::assertSame(CarbonImmutable::now(), $this->domainDeployment->last_result_received);
        self::assertSame($exceptionMessage, $this->domainDeployment->last_result);
    }

    #[test]
    public function testRestoreDomainJobAutoRenewOffButActiveDomain(): void
    {
        $domainAutoRenewOff = include __DIR__ . '/data/domain_details_auto_renew_off_status_ok.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetailsAutoRenewOff = $serializer->denormalize($domainAutoRenewOff, DomainDetailsDTO::class);

        $domainService = self::createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            )
            ->willReturn($domainDetailsAutoRenewOff);
        $domainService->expects(self::once())->method('enableAutoRenewal');
        $domainService
            ->expects(self::never())
            ->method('restore')
            ->with(
                $this->domainDeployment->subscription->domain,
                $this->domainDeployment->provider->slug,
            );

        $job = new RestoreDomainJob($this->domainDeployment);
        $job->handle($domainService, self::createStub(LoggerInterface::class));

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
    }
}
