<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Microsoft365;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
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
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(NovaAddMissingTenantOrderIdAction::class)]
class NovaAddMissingTenantOrderIdActionTest extends IntegrationTestCase
{
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createMock(LoggerInterface::class);
    }

    #[Test]
    public function handleNoMatchingCustomerInfos(): void
    {
        Queue::fake();

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Executing one-time script add-missing-tenant-order-id in execution mode',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'add-missing-tenant-order-id',
                    LoggingContextKeys::META => ['dry-run' => false],
                ],
            );

        $action = new NovaAddMissingTenantOrderIdAction(
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: $this->logger,
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false, 'amount' => 500]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('No active Microsoft365 customer infos found with missing tenant order id.', (string) $responseData['message']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function handleDispatchesJobForEachMatchingCustomerInfo(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();

        new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => null,
                'kpn_customer_id' => 'CID123456',
            ]);

        new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => null,
                'kpn_customer_id' => 'CID789012',
            ]);

        // Should NOT be dispatched: has tenant_order_id
        new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => 12345678,
                'kpn_customer_id' => 'CID345678',
            ]);

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $action = new NovaAddMissingTenantOrderIdAction(
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: $this->logger,
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false, 'amount' => 500]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('Found 2 customer infos with missing tenant order id. Updating will be done async.', (string) $responseData['message']);

        Queue::assertPushed(AddMissingTenantOrderIdJob::class, 2);
    }

    #[Test]
    public function handleDryRunReturnsCountWithoutDispatchingJobs(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();

        new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => null,
                'kpn_customer_id' => 'CID123456',
            ]);

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Executing one-time script add-missing-tenant-order-id in dry-run mode',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'add-missing-tenant-order-id',
                    LoggingContextKeys::META => ['dry-run' => true],
                ],
            );

        $action = new NovaAddMissingTenantOrderIdAction(
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: $this->logger,
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => true, 'amount' => 500]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('Dry run found 1 customer infos with missing tenant order id.', (string) $responseData['message']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function handleIgnoresCustomerInfosWithNullKpnCustomerId(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();

        new Microsoft365CustomerInfoFactory()
            ->for($customer)
            ->createOne([
                'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                'tenant_order_id' => null,
                'kpn_customer_id' => null,
            ]);

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $action = new NovaAddMissingTenantOrderIdAction(
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: $this->logger,
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false, 'amount' => 500]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('No active Microsoft365 customer infos found with missing tenant order id.', (string) $responseData['message']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function handleRespectsLimit(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();

        for ($i = 0; $i < 5; $i++) {
            new Microsoft365CustomerInfoFactory()
                ->for($customer)
                ->createOne([
                    'technical_status' => Microsoft365ProcessStatus::ACTIVE,
                    'tenant_order_id' => null,
                    'kpn_customer_id' => 'CID' . ($i + 1),
                ]);
        }

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $action = new NovaAddMissingTenantOrderIdAction(
            jobDispatcher: self::resolve(Dispatcher::class),
            logger: $this->logger,
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false, 'amount' => 2]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('Found 2 customer infos with missing tenant order id. Updating will be done async.', (string) $responseData['message']);

        Queue::assertPushed(AddMissingTenantOrderIdJob::class, 2);
    }
}
