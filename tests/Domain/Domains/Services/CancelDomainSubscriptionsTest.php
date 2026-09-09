<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Jobs\CancelDomainSubscriptionJob;
use Waterfront\Domain\Domains\Services\CancelDomainDeployments;

#[CoversClass(CancelDomainSubscriptionJob::class)]
#[AllowMockObjectsWithoutExpectations]
class CancelDomainSubscriptionsTest extends IntegrationTestCase
{
    public const string EXAMPLE_DOMAIN = 'example.com';

    private Dispatcher&MockObject $dispatcher;

    private LoggerInterface&MockObject $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->createMock(Dispatcher::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    #[Test]
    public function cancelFromStreamSuccessful(): void
    {
        $resource = fopen(__DIR__ . '/data/success.csv', 'r');
        assert($resource !== false);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new CancelDomainSubscriptionJob('example.com')
            );

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    CancelDomainDeployments::DISPATCH_JOB_MSG,
                    self::EXAMPLE_DOMAIN
                )
            );

        $service = new CancelDomainDeployments(
            $this->dispatcher,
            $this->logger
        );
        $service->cancelFromStream(false, $resource);
    }

    #[Test]
    public function cancelFromStreamDryRun(): void
    {
        $resource = fopen(__DIR__ . '/data/success.csv', 'r');
        assert($resource !== false);

        $this->dispatcher->expects(self::never())
            ->method('dispatch')
            ->with(
                new CancelDomainSubscriptionJob('example.com')
            );

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    CancelDomainDeployments::DRY_RUN_MSG,
                    self::EXAMPLE_DOMAIN
                )
            );

        $service = new CancelDomainDeployments(
            $this->dispatcher,
            $this->logger
        );
        $service->cancelFromStream(true, $resource);
    }

    #[Test]
    public function cancelFromStreamWithInvalidCsv(): void
    {
        $resource = fopen(__DIR__ . '/data/to-many-columns.csv', 'r');
        assert($resource !== false);

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    CancelDomainDeployments::TOO_MANY_COLUMNS_ERROR,
                    1,
                    2
                )
            );

        $service = new CancelDomainDeployments(
            $this->dispatcher,
            $this->logger
        );
        $this->expectException(RuntimeException::class);
        $service->cancelFromStream(false, $resource);
    }
}
