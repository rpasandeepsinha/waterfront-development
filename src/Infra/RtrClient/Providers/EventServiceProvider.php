<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Listeners\CertificateRequestNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\CreateDomainNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\DeleteDomainNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\RenewDomainNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\TransferredOutNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\UpdateDomainNotificationListener;
use Waterfront\Infra\RtrClient\Listeners\ValidateContactNotificationListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        NewNotificationReceived::class => [
            CreateDomainNotificationListener::class,
            DeleteDomainNotificationListener::class,
            UpdateDomainNotificationListener::class,
            RenewDomainNotificationListener::class,
            TransferredOutNotificationListener::class,
            CertificateRequestNotificationListener::class,
            ValidateContactNotificationListener::class,
        ],
    ];
}
