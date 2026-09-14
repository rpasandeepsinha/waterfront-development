<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Listeners\AddChangedSubscriptionInvoice;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(AddChangedSubscriptionInvoice::class)]
class AddChangedSubscriptionInvoiceTest extends TestCase
{
    #[Test]
    public function subscriptionChangedForDowngrade(): void
    {
        $now = CarbonImmutable::now()->setTime(12, 1, 2);
        CarbonImmutable::setTestNow($now);

        $subscription = self::createStub(Subscription::class);

        $invoiceRepository = self::createMock(InvoiceRepository::class);
        $invoiceRepository
            ->expects(self::once())
            ->method('createInvoiceForChangedSubscription')
            ->with(
                self::equalTo($subscription),
                100,
                ProductChangeType::DOWNGRADE,
                $now,
            );

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $event = new SubscriptionChangedEvent(
            $subscription,
            100,
            ProductChangeType::DOWNGRADE,
        );

        $listener = new AddChangedSubscriptionInvoice($invoiceRepository, $dispatcher);
        $listener->handle($event);
    }

    #[Test]
    public function subscriptionChangedForUpgrade(): void
    {
        $now = CarbonImmutable::now()->setTime(12, 1, 2);
        CarbonImmutable::setTestNow($now);

        $subscription = self::createStub(Subscription::class);
        $invoice = self::createStub(Invoice::class);

        $invoiceRepository = self::createMock(InvoiceRepository::class);
        $invoiceRepository
            ->expects(self::once())
            ->method('createInvoiceForChangedSubscription')
            ->with(
                self::identicalTo($subscription),
                200,
                ProductChangeType::UPGRADE,
                $now,
            )
            ->willReturn($invoice);

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (InvoiceCreatedEvent $invoiceCreatedEvent) use ($invoice): bool {
                    self::assertSame($invoiceCreatedEvent->invoice, $invoice);
                    self::assertFalse($invoiceCreatedEvent->isRenewed);

                    return true;
                }),
            );

        $event = new SubscriptionChangedEvent(
            $subscription,
            200,
            ProductChangeType::UPGRADE,
        );

        $listener = new AddChangedSubscriptionInvoice($invoiceRepository, $dispatcher);
        $listener->handle($event);
    }
}
