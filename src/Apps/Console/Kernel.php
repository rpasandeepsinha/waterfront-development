<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Waterfront\Apps\Console\Commands\Debug\CartCheck;
use Waterfront\Apps\Console\Commands\Debug\PdnsCrud;
use Waterfront\Apps\Console\Commands\Debug\ProductPrice;
use Waterfront\Apps\Console\Commands\Domains\DisableDomainAutoRenewal;
use Waterfront\Apps\Console\Commands\Email\CleanEmailHistoryPayloadData;
use Waterfront\Apps\Console\Commands\EnvironmentCheck;
use Waterfront\Apps\Console\Commands\Harbor\Consume;
use Waterfront\Apps\Console\Commands\Harbor\DispatchInvoicesToHarbor;
use Waterfront\Apps\Console\Commands\Harbor\ReportInvoiceablesInProgress;
use Waterfront\Apps\Console\Commands\HubSpot\HubspotBatchSyncSubscriptions;
use Waterfront\Apps\Console\Commands\HubSpot\HubspotPruneEvents;
use Waterfront\Apps\Console\Commands\MakeOneOffScript;
use Waterfront\Apps\Console\Commands\Microsoft365SyncWatcher;
use Waterfront\Apps\Console\Commands\PollNotifications;
use Waterfront\Apps\Console\Commands\Products\UpdateProductsS3;
use Waterfront\Apps\Console\Commands\Sanity\CheckSsl;
use Waterfront\Apps\Console\Commands\SeedCommand;
use Waterfront\Apps\Console\Commands\SSL\ReissueExpiringSslCertificates;
use Waterfront\Apps\Console\Commands\SSL\SendDcvReminderEmails;
use Waterfront\Apps\Console\Commands\Subscriptions\AdministrativelyExpireSubscriptions;
use Waterfront\Apps\Console\Commands\Subscriptions\CreateSubscriptionInvoices;
use Waterfront\Apps\Console\Commands\Subscriptions\ProcessTechnicalMutations;
use Waterfront\Apps\Console\Commands\Subscriptions\RenewSubscriptions;
use Waterfront\Apps\Console\Commands\Subscriptions\SendManualTerminationReminder;
use Waterfront\Apps\Console\Commands\Subscriptions\TerminateSubscriptions;
use Waterfront\Apps\Console\Commands\Translations\TranslationsToDatabase;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;
use Waterfront\Apps\Console\Commands\UpdateObjectStorageItems;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Configuration\Configuration;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<mixed>
     */
    protected $commands = [
        SeedCommand::class,
        DisableDomainAutoRenewal::class,
        RenewSubscriptions::class,
        CreateSubscriptionInvoices::class,
        TerminateSubscriptions::class,
        TranslationsToDatabase::class,
        PdnsCrud::class,
        ProductPrice::class,
        CartCheck::class,
        Consume::class,
        CheckSsl::class,
        PollNotifications::class,
        SendManualTerminationReminder::class,
        UpdateTranslationsS3::class,
        UpdateProductsS3::class,
        UpdateObjectStorageItems::class,
        EnvironmentCheck::class,
        ReportInvoiceablesInProgress::class,
        DispatchInvoicesToHarbor::class,
        Microsoft365SyncWatcher::class,
        AdministrativelyExpireSubscriptions::class,
        CleanEmailHistoryPayloadData::class,
        SendDcvReminderEmails::class,
        HubspotBatchSyncSubscriptions::class,
        HubspotPruneEvents::class,
        ProcessTechnicalMutations::class,
        ReissueExpiringSslCertificates::class,
        MakeOneOffScript::class,
    ];

    public function bootstrap(): void
    {
        parent::bootstrap();

        // Web requests can call artisan commands, resulting in this method being called.
        // Checking if we're in console context and if an authenticated subject is already set is necessary,
        // so we don't overwrite it.
        if (App::runningInConsole()) {
            /** @var AuthenticationManager $authenticationManager */
            $authenticationManager = $this->app->make(AuthenticationManager::class);

            try {
                $authenticationManager->getAuthenticatedSubject();
            } catch (AuthenticationException) {
                $authenticationManager->handleConsole();
            }
        }
    }

    protected function schedule(Schedule $schedule): void
    {
        $configuration = $this->app->get(Configuration::class);

        if (! $configuration->getAsBoolean('app.schedule_allowed_to_run')) {
            if ($configuration->getAsString('app.env') === 'local') {
                // We don't want to spam our local log with messages.
                return;
            }

            Log::debug('Scheduling is currently not enabled see env var `SCHEDULE_ALLOWED_TO_RUN`');

            return;
        }

        $schedule->command(RenewSubscriptions::class)->dailyAt('00:00')->runInBackground()->sentryMonitor();

        $schedule->command(CreateSubscriptionInvoices::class)->dailyAt('00:30')->runInBackground()->sentryMonitor();

        $schedule
            ->command(AdministrativelyExpireSubscriptions::class)
            ->dailyAt('01:00')
            ->runInBackground()
            ->sentryMonitor();

        $schedule->command(TerminateSubscriptions::class)->dailyAt('01:30')->runInBackground()->sentryMonitor();

        $schedule->command(ProcessTechnicalMutations::class)->dailyAt('02:15')->runInBackground()->sentryMonitor();

        $schedule->command(DisableDomainAutoRenewal::class)->dailyAt('02:30')->runInBackground()->sentryMonitor();

        $schedule->command(Microsoft365SyncWatcher::class)->dailyAt('03:30')->runInBackground()->sentryMonitor();

        $schedule
            ->command(CleanEmailHistoryPayloadData::class)
            ->dailyAt('4:00')
            ->runInBackground()
            ->withoutOverlapping();

        $schedule->command(ReissueExpiringSslCertificates::class)->dailyAt('04:30')->runInBackground()->sentryMonitor();

        $schedule->command(SendManualTerminationReminder::class)->dailyAt('05:00')->runInBackground()->sentryMonitor();

        $schedule->command(DispatchInvoicesToHarbor::class)->dailyAt('06:00')->runInBackground()->sentryMonitor();

        $schedule->command(ReportInvoiceablesInProgress::class)->dailyAt('06:30')->runInBackground()->sentryMonitor();

        $schedule->command('horizon:snapshot')->everyFiveMinutes()->runInBackground()->sentryMonitor();

        $schedule
            ->command(Consume::class)
            ->everyMinute()
            ->sentryMonitor()
            ->withoutOverlapping()
            ->when(fn (): bool => (bool) Config::get('harbor.enabled'));

        $schedule
            ->command(PollNotifications::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->sentryMonitor()
            ->when(function () use ($configuration): bool {
                $apiURL = $configuration->getAsString('realtimeregisterclient.connection.api_url');
                $apiKey = $configuration->getAsString('realtimeregisterclient.connection.api_key');

                if ($apiKey === '' || $apiURL === '') {
                    return false;
                }

                return true;
            });

        $schedule
            ->command(UpdateTranslationsS3::class)
            ->daily()
            ->withoutOverlapping()
            ->runInBackground()
            ->sentryMonitor();

        $schedule->command(UpdateProductsS3::class)->everyThirtyMinutes()->runInBackground()->sentryMonitor();

        $schedule
            ->command(HubspotBatchSyncSubscriptions::class)
            ->everyTenMinutes()
            ->between('5:00', '22:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->sentryMonitor()
            ->when(fn (): bool => $this->hasValidHubSpotConfig());

        $schedule
            ->command(HubspotBatchSyncSubscriptions::class)
            ->daily()
            ->at('23:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->sentryMonitor();

        $schedule->command('cache:prune-stale-tags')->hourly()->sentryMonitor();

        $schedule
            ->command('model:prune', [
                '--model' => [CloudstackJob::class, Microsoft365SyncLog::class],
            ])
            ->sentryMonitor()
            ->daily();

        $schedule
            ->command(SendDcvReminderEmails::class)
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->sentryMonitor();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function hasValidHubSpotConfig(): bool
    {
        $configuration = $this->app->get(Configuration::class);

        $accessToken = $configuration->getAsString('services.hubspot.access_token');
        $objectId = $configuration->getAsString('services.hubspot.subscription_object_type_id');
        $contactId = $configuration->getAsString('services.hubspot.subscription_contact_id');

        return ! ($accessToken === '' || $objectId === '' || $contactId === '');
    }
}
