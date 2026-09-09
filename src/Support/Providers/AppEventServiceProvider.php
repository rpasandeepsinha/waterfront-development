<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Ferry\Listeners\MigrationJobEventListener;
use Waterfront\Domain\Harbor\Listeners\DispatchToHarbor;
use Waterfront\Domain\Harbor\Listeners\InvoiceCreatedListener;
use Waterfront\Domain\Harbor\Listeners\SendCreditInvoiceListener;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Listeners\AddChangedSubscriptionInvoice;
use Waterfront\Domain\Ssl\Listeners\RenewSslRequest;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Domain\Subscriptions\Events\SubscriptionRenewedEvent;
use Waterfront\Infra\Authentication\Listeners\ResetConsoleAuthenticationListener;
use Waterfront\Infra\Logging\EventListener\LogJobEventListener;
use Waterfront\Infra\Logging\EventListener\ResetLoggerJobEventListener;
use Waterfront\Infra\Logging\EventListener\ScheduledTaskEventListener;

class AppEventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        SubscriptionRenewedEvent::class => [
            RenewSslRequest::class,
        ],
        SubscriptionChangedEvent::class => [
            AddChangedSubscriptionInvoice::class,
            SendCreditInvoiceListener::class,
        ],
        InvoiceCreatedEvent::class => [
            InvoiceCreatedListener::class,
        ],
        CustomerDataChangedEvent::class => [
            DispatchToHarbor::class,
        ],
        JobFailed::class => [
            LogJobEventListener::class,
            ResetLoggerJobEventListener::class,
            MigrationJobEventListener::class,
        ],
        JobProcessing::class => [
            LogJobEventListener::class,
            ResetConsoleAuthenticationListener::class,
        ],
        JobProcessed::class => [
            LogJobEventListener::class,
            ResetLoggerJobEventListener::class,
            MigrationJobEventListener::class,
        ],
        JobExceptionOccurred::class => [
            ResetLoggerJobEventListener::class,
            LogJobEventListener::class,
        ],
        JobReleasedAfterException::class => [
            ResetLoggerJobEventListener::class,
        ],
        JobTimedOut::class => [
            LogJobEventListener::class,
        ],
        ScheduledTaskStarting::class => [
            ScheduledTaskEventListener::class,
        ],
        ScheduledTaskFinished::class => [
            ScheduledTaskEventListener::class,
        ],
        ScheduledTaskFailed::class => [
            ScheduledTaskEventListener::class,
        ],
    ];
}
