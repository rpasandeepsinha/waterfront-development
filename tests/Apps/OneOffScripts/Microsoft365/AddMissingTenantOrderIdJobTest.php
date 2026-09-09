<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Microsoft365;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Microsoft365\AddMissingTenantOrderIdJob;
use Waterfront\Apps\OneOffScripts\Microsoft365\NovaAddMissingTenantOrderIdAction;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(AddMissingTenantOrderIdJob::class)]
#[AllowMockObjectsWithoutExpectations]
class AddMissingTenantOrderIdJobTest extends IntegrationTestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&Microsoft365Service $microsoft365Service;

    private Microsoft365CustomerInfo $customerInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createMock(LoggerInterface::class);
        $this->microsoft365Service = self::createMock(Microsoft365Service::class);

        $customer = new CustomerFactory()->createOne();
        $this->customerInfo = new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => null,
                'kpn_customer_id' => 'CID123456',
            ]);
    }

    #[Test]
    public function jobIsDispatchedOnOneTimeScriptsQueue(): void
    {
        Queue::fake();

        self::resolve(Dispatcher::class)->dispatch(new AddMissingTenantOrderIdJob(
            microsoft365CustomerInfo: $this->customerInfo,
        ));

        Queue::assertPushedOn(QueueName::ONE_TIME_SCRIPTS->value, AddMissingTenantOrderIdJob::class);
    }

    #[Test]
    public function jobIsAsync(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)->dispatch(new AddMissingTenantOrderIdJob(
            microsoft365CustomerInfo: $this->customerInfo,
        ));

        Bus::assertNotDispatchedSync(AddMissingTenantOrderIdJob::class);
    }

    #[Test]
    public function handleUpdatesTenantOrderIdWhenTenantOrderFound(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantOrderId')
            ->with(123456)
            ->willReturn(99887766);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Found tenant order id [99887766] for KPN customer id: [CID123456]',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                    LoggingContextKeys::META => [
                        'customer_info_id' => $this->customerInfo->id,
                        'kpn_customer_id' => 'CID123456',
                        'tenant_order_id' => 99887766,
                    ],
                ],
            );

        $job = new AddMissingTenantOrderIdJob(
            microsoft365CustomerInfo: $this->customerInfo,
        );

        $job->handle($this->logger, $this->microsoft365Service);

        $this->customerInfo->refresh();
        self::assertSame(99887766, $this->customerInfo->tenant_order_id);
    }

    #[Test]
    public function handleLogsInfoWhenNoTenantOrderFound(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantOrderId')
            ->with(123456)
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'No tenant order found for KPN customer id: [CID123456]',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                    LoggingContextKeys::META => [
                        'customer_info_id' => $this->customerInfo->id,
                        'kpn_customer_id' => 'CID123456',
                    ],
                ],
            );

        $job = new AddMissingTenantOrderIdJob(
            microsoft365CustomerInfo: $this->customerInfo,
        );

        $job->handle($this->logger, $this->microsoft365Service);

        $this->customerInfo->refresh();
        self::assertNull($this->customerInfo->tenant_order_id);
    }

    #[Test]
    public function handleLogsInfoWhenNoOrdersReturned(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantOrderId')
            ->with(123456)
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'No tenant order found for KPN customer id: [CID123456]',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaAddMissingTenantOrderIdAction::SLUG,
                    LoggingContextKeys::META => [
                        'customer_info_id' => $this->customerInfo->id,
                        'kpn_customer_id' => 'CID123456',
                    ],
                ],
            );

        $job = new AddMissingTenantOrderIdJob(
            microsoft365CustomerInfo: $this->customerInfo,
        );

        $job->handle($this->logger, $this->microsoft365Service);

        $this->customerInfo->refresh();
        self::assertNull($this->customerInfo->tenant_order_id);
    }
}
