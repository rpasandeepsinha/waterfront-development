<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Validation;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Jobs\ValidationJob;
use Waterfront\Domain\Ferry\Pipes\BackupPipe;
use Waterfront\Domain\Ferry\Pipes\CustomerPipe;
use Waterfront\Domain\Ferry\Pipes\DnsConfigurationPipe;
use Waterfront\Domain\Ferry\Pipes\DnsSecEnablePipe;
use Waterfront\Domain\Ferry\Pipes\DomainMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\HostingMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\MailOnlyMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\NameserverMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\RedirectMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\ResellerHostingMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\SitebuilderMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\SslMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\SubscriptionPipe;

class ExecuteValidationAction
{
    /** @var string[] */
    public const array PIPES = [
        CustomerPipe::class,
        SubscriptionPipe::class,
        BackupPipe::class,
        DomainMigrationPipe::class,
        DnsConfigurationPipe::class,
        NameserverMigrationPipe::class,
        DnsSecEnablePipe::class,
        HostingMigrationPipe::class,
        MailOnlyMigrationPipe::class,
        SslMigrationPipe::class,
        RedirectMigrationPipe::class,
        SitebuilderMigrationPipe::class,
        ResellerHostingMigrationPipe::class,
    ];

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function execute(ValidationPayload $payload): void
    {
        $job = new ValidationJob(
            validationPayload: $payload,
            pipes: self::PIPES,
        );

        $this->jobDispatcher->dispatch($job);
    }
}
