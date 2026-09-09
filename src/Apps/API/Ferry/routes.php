<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Ferry\Controllers\BackupMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\ConfigureDnsBulkMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\CustomerBulkController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\DomainBulkMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\HostingBulkMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\NameserverBulkMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\SubscriptionBulkController;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\ValidationBulkController;
use Waterfront\Apps\API\Ferry\Controllers\ConfigureDnsController;
use Waterfront\Apps\API\Ferry\Controllers\CustomerController;
use Waterfront\Apps\API\Ferry\Controllers\CustomerInvoiceController;
use Waterfront\Apps\API\Ferry\Controllers\DomainMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\EnableDnsSecController;
use Waterfront\Apps\API\Ferry\Controllers\HostingMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\MailOnlyMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\NameserverMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\RedirectMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\ResellerHostingMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\SitebuilderMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\SslMigrationController;
use Waterfront\Apps\API\Ferry\Controllers\SubscriptionController;
use Waterfront\Apps\API\Ferry\Controllers\ValidationController;
use Waterfront\Apps\API\Kernel;

$domain = Config::get('ferry.url_ferry_api');
assert(is_string($domain));

Route::domain($domain)
    ->middleware([Kernel::MIDDLEWARE_GROUP_FERRY_API])
    ->as('ferry.')
    ->prefix('ferry')
    ->group(function (): void {
        Route::prefix('customers')->as('customers.')->group(function (): void {
            Route::post('/', [CustomerController::class, 'create'])->name('create');

            Route::post('/bulk', [CustomerBulkController::class, 'create'])->name('create.bulk');
            Route::post('/validate/bulk', [ValidationBulkController::class, 'create'])->name('validate.bulk');

            Route::post('/validate', [ValidationController::class, 'validate'])->name('validate');

            Route::prefix('{customer:id}')->group(function (): void {
                Route::prefix('subscriptions')->as('subscriptions.')->group(function (): void {
                    Route::post('/', [SubscriptionController::class, 'create'])->name('create');

                    Route::post('/migrate_domains', [DomainMigrationController::class, 'execute'])->name('migrate_domain');

                    Route::post('/configure_dns', [ConfigureDnsController::class, 'configureDns'])->name('configure_dns');

                    Route::post('/enable_dnssec', [EnableDnsSecController::class, 'enable'])->name('enable_dnssec');

                    Route::post('/migrate_nameservers', [NameserverMigrationController::class, 'migrateNameservers'])->name('migrate_nameservers');

                    Route::post('/migrate_backups', [BackupMigrationController::class, 'execute'])->name('migrate_backups');

                    Route::post('/migrate_hosting', [HostingMigrationController::class, 'execute'])->name('migrate_hosting');

                    Route::post('/migrate_reseller_hosting', [ResellerHostingMigrationController::class, 'execute'])->name('migrate_reseller_hosting');

                    Route::post('/migrate_mail_hosting', [MailOnlyMigrationController::class, 'execute'])->name('migrate_mail_only');

                    Route::post('/migrate_ssl', [SslMigrationController::class, 'execute'])->name('migrate_ssl');

                    Route::post('/migrate_redirects', [RedirectMigrationController::class, 'execute'])->name('migrate_redirects');

                    Route::post('/migrate_sitebuilder', [SitebuilderMigrationController::class, 'execute'])->name('migrate_sitebuilder');
                });

                Route::prefix('invoices')->as('invoices.')->group(function (): void {
                    Route::post('/enable', [CustomerInvoiceController::class, 'enableInvoicing'])->name('enable');
                });
            });

            Route::prefix('subscriptions')->as('subscriptions.')->group(function (): void {
                Route::post('/bulk', [SubscriptionBulkController::class, 'create'])->name('create.bulk');
                Route::post('/migrate_domains/bulk', [DomainBulkMigrationController::class, 'execute'])->name('migrate_domain.bulk');
                Route::post('/configure_dns/bulk', [ConfigureDnsBulkMigrationController::class, 'execute'])->name('configure_dns.bulk');
                Route::post('/migrate_nameservers/bulk', [NameserverBulkMigrationController::class, 'execute'])->name('migrate_nameservers.bulk');
                Route::post('/migrate_hosting/bulk', [HostingBulkMigrationController::class, 'execute'])->name('migrate_hosting.bulk');
            });
        });
    });
