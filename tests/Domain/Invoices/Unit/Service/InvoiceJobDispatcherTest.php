<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Unit\Service;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Jobs\CreateSubscriptionInvoicesForCustomer;
use Waterfront\Domain\Invoices\Services\InvoiceJobDispatcher;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(InvoiceJobDispatcher::class)]
class InvoiceJobDispatcherTest extends TestCase
{
    #[Test]
    public function dispatchJobsWillDispatchJobForSingleCustomer(): void
    {
        $customers = new Collection([
            new Customer(['id' => '1']),
        ]);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('getAllCustomersEligibleForInvoicing')->willReturn($customers);

        $bus = $this->createMock(Dispatcher::class);
        $bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateSubscriptionInvoicesForCustomer::class));

        $configuration = $this->createStub(ConfigurationInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $dispatcher = new InvoiceJobDispatcher($repository, $bus, $configuration, $logger);
        $dispatcher->dispatchJobs();
    }

    #[Test]
    public function dispatchJobsWillDispatchJobsForMultipleCustomers(): void
    {
        $customers = new Collection([
            new Customer(['id' => '1']),
            new Customer(['id' => '2']),
        ]);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('getAllCustomersEligibleForInvoicing')->willReturn($customers);

        $bus = $this->createMock(Dispatcher::class);
        $bus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(
                self::isInstanceOf(CreateSubscriptionInvoicesForCustomer::class),
            );

        $configuration = $this->createStub(ConfigurationInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $dispatcher = new InvoiceJobDispatcher($repository, $bus, $configuration, $logger);
        $dispatcher->dispatchJobs();
    }

    #[Test]
    public function dispatchJobsChecksCorrectBillingDate(): void
    {
        $now = new CarbonImmutable('2023-01-02 12:34:56');
        CarbonImmutable::setTestNow($now);

        $billingDate = new CarbonImmutable('2023-01-16 00:00:00');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects(self::once())
            ->method('getAllCustomersEligibleForInvoicing')
            ->with(self::equalTo($billingDate));

        $bus = $this->createStub(Dispatcher::class);

        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->expects(self::once())->method('getAsInteger')->willReturn(14);

        $logger = $this->createStub(LoggerInterface::class);

        $dispatcher = new InvoiceJobDispatcher($repository, $bus, $configuration, $logger);
        $dispatcher->dispatchJobs();
    }

    #[Test]
    public function dispatchJobsWillNotDispatchJobsWhenNoCustomersFound(): void
    {
        $customers = new Collection([]);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('getAllCustomersEligibleForInvoicing')->willReturn($customers);

        $bus = $this->createMock(Dispatcher::class);
        $bus->expects(self::never())->method('dispatch');

        $configuration = $this->createStub(ConfigurationInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $dispatcher = new InvoiceJobDispatcher($repository, $bus, $configuration, $logger);
        $dispatcher->dispatchJobs();
    }
}
