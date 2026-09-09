<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Waterfront\Domain\Backup\Events\BackupTerminateEvent;
use Waterfront\Domain\Backup\Events\CreateBackup;
use Waterfront\Domain\Backup\Listeners\BackupCreationListener;
use Waterfront\Domain\Backup\Listeners\BackupTerminationListener;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Events\TerminateDnsZoneEvent;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Events\ZoneOutdated;
use Waterfront\Domain\DNS\Listeners\DnsCreationListener;
use Waterfront\Domain\DNS\Listeners\DnsProvisionedListener;
use Waterfront\Domain\DNS\Listeners\DnsTerminationListener;
use Waterfront\Domain\DNS\Listeners\DnsUpdatingListener;
use Waterfront\Domain\DNS\Listeners\HostingDnsUpdatingListener;
use Waterfront\Domain\DNS\Listeners\TemplateListener;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Events\DomainTerminated;
use Waterfront\Domain\Domains\Listeners\DomainCreationListener;
use Waterfront\Domain\Domains\Listeners\DomainTerminatedListener;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Hosting\Events\TerminateHosting;
use Waterfront\Domain\Hosting\Listeners\HostingCreationListener;
use Waterfront\Domain\Hosting\Listeners\HostingTerminationListener;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\MailManagement\Events\TerminateMailOnlyHosting;
use Waterfront\Domain\MailManagement\Listeners\HostingMailOnlyCreationListener;
use Waterfront\Domain\MailManagement\Listeners\HostingMailOnlyTerminationListener;
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Events\DispatchTerminateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Listeners\ManualSubscriptionCreationListener;
use Waterfront\Domain\ManualProvisioning\Listeners\ManualSubscriptionTerminationListener;
use Waterfront\Domain\Microsoft365\Events\CreateMicrosoft365;
use Waterfront\Domain\Microsoft365\Events\Microsoft365Webhook;
use Waterfront\Domain\Microsoft365\Events\TerminateMicrosoft365;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365CreationListener;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365TerminationListener;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365WebhookLogListener;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting;
use Waterfront\Domain\Sitebuilder\Listeners\SitebuilderCreationListener;
use Waterfront\Domain\Sitebuilder\Listeners\SitebuilderTerminationListener;
use Waterfront\Domain\Ssl\Events\CreateSsl;
use Waterfront\Domain\Ssl\Listeners\SslCreationListener;
use Waterfront\Domain\VPS\Events\CreateVps;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;
use Waterfront\Domain\VPS\Listeners\VpsCreationListener;
use Waterfront\Domain\VPS\Listeners\VpsTerminationListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        CreateBackup::class => [
            BackupCreationListener::class,
        ],
        CreateHosting::class => [
            HostingCreationListener::class,
        ],
        CreateMailOnlyHosting::class => [
            HostingMailOnlyCreationListener::class,
        ],
        CreateSitebuilder::class => [
            SitebuilderCreationListener::class,
        ],
        TerminateHosting::class => [
            HostingTerminationListener::class,
        ],
        TerminateMailOnlyHosting::class => [
            HostingMailOnlyTerminationListener::class,
        ],
        TerminateSitebuilderHosting::class => [
            SitebuilderTerminationListener::class,
            HostingMailOnlyTerminationListener::class,
        ],
        CreateSsl::class => [
            SslCreationListener::class,
        ],
        CreateDns::class => [
            DnsCreationListener::class,
        ],
        CreateDomain::class => [
            DomainCreationListener::class,
        ],
        DomainTerminated::class => [
            DomainTerminatedListener::class,
        ],
        UpdateDns::class => [
            DnsUpdatingListener::class,
        ],
        ReplaceParkingAndUpdateDns::class => [
            HostingDnsUpdatingListener::class,
        ],
        ZoneOutdated::class => [
            TemplateListener::class,
        ],
        CreateVps::class => [
            VpsCreationListener::class,
        ],
        CreateMicrosoft365::class => [
            Microsoft365CreationListener::class,
        ],
        TerminateMicrosoft365::class => [
            Microsoft365TerminationListener::class,
        ],
        Microsoft365Webhook::class => [
            Microsoft365WebhookLogListener::class,
        ],
        DispatchCreateManualProvisioning::class => [
            ManualSubscriptionCreationListener::class,
        ],
        DispatchTerminateManualProvisioning::class => [
            ManualSubscriptionTerminationListener::class,
        ],
        VpsTerminateEvent::class => [
            VpsTerminationListener::class,
        ],
        TerminateDnsZoneEvent::class => [
            DnsTerminationListener::class,
        ],
        DnsProvisioned::class => [
            DnsProvisionedListener::class,
        ],
        BackupTerminateEvent::class => [
            BackupTerminationListener::class,
        ],
    ];
}
