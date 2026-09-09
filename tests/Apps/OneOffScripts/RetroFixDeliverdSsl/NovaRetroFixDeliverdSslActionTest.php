<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\RetroFixDeliverdSsl;

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
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl\NovaRetroFixDeliverdSslAction;
use Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl\RetroFixDeliveredSslJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(NovaRetroFixDeliverdSslAction::class)]
class NovaRetroFixDeliverdSslActionTest extends IntegrationTestCase
{
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createMock(LoggerInterface::class);
    }

    #[Test]
    public function handleNoMatchingSubscriptions(): void
    {
        Queue::fake();

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Executing one-time script retro-fix-deliverd-ssl',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => ['dry-run' => false],
                ],
            );

        $action = new NovaRetroFixDeliverdSslAction(
            logger: $this->logger,
            dispatcher: self::resolve(Dispatcher::class),
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('One-off script executed successfully.', (string) $responseData['message']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function handleDispatchesJobForEachMatchingSubscription(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example1.com')
            ->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example2.com')
            ->createOne();

        // Should NOT be dispatched (already OK)
        SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('already-ok.com')
            ->createOne();

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $action = new NovaRetroFixDeliverdSslAction(
            logger: $this->logger,
            dispatcher: self::resolve(Dispatcher::class),
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('One-off script executed successfully.', (string) $responseData['message']);

        Queue::assertPushed(RetroFixDeliveredSslJob::class, 2);
    }

    #[Test]
    public function handleDryRunDispatchesJobsAndReturnsDryRunMessage(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example.com')
            ->createOne();

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Executing one-time script retro-fix-deliverd-ssl',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => ['dry-run' => true],
                ],
            );

        $action = new NovaRetroFixDeliverdSslAction(
            logger: $this->logger,
            dispatcher: self::resolve(Dispatcher::class),
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => true]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('Dry run completed.', (string) $responseData['message']);

        Queue::assertPushed(RetroFixDeliveredSslJob::class, 1);
    }

    #[Test]
    public function handleIgnoresSubscriptionsWithTechnicalStatusOk(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('already-ok.com')
            ->createOne();

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Executing one-time script retro-fix-deliverd-ssl',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => ['dry-run' => false],
                ],
            );

        $action = new NovaRetroFixDeliverdSslAction(
            logger: $this->logger,
            dispatcher: self::resolve(Dispatcher::class),
        );

        $actionResponse = $action->handle(new ActionFields(
            new Collection(['dry-run' => false]),
            new Collection(),
        ));

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('One-off script executed successfully.', (string) $responseData['message']);

        Queue::assertNothingPushed();
    }
}
