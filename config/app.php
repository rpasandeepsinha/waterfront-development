<?php

declare(strict_types=1);

use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Providers\ConsoleSupportServiceProvider;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pagination\PaginationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Waterfront\Apps\API\Compass\Providers\CompassServiceProvider;
use Waterfront\Apps\API\Ferry\Providers\FerryServiceProvider;
use Waterfront\Apps\API\Harbor\Providers\HarborServiceProvider;
use Waterfront\Apps\API\Partners\Providers\PartnersServiceProvider;
use Waterfront\Apps\Nova\NovaServiceProvider;
use Waterfront\Apps\Webhooks\Providers\WebhooksServiceProvider;
use Waterfront\Domain\DNS\Providers\DnsServiceProvider;
use Waterfront\Domain\Domains\Providers\DomainServiceProvider;
use Waterfront\Domain\Ferry\Providers\FerryDomainServiceProvider;
use Waterfront\Domain\Hosting\DirectAdmin\Providers\DirectAdminHostingServiceProvider;
use Waterfront\Domain\Hosting\Plesk\Providers\HostingServiceProvider;
use Waterfront\Domain\Lighthouse\Providers\LighthouseServiceProvider;
use Waterfront\Domain\MailManagement\Providers\MailManagementServiceProvider;
use Waterfront\Domain\ManualProvisioning\Providers\ManualProvisioningServiceProvider;
use Waterfront\Domain\Microsoft365\Providers\Microsoft365Provider;
use Waterfront\Domain\Payments\Providers\PaymentServiceServiceProvider;
use Waterfront\Domain\Products\ProductListUpdaterProvider;
use Waterfront\Domain\Redirects\Providers\RedirectsProvider;
use Waterfront\Domain\Sitebuilder\Providers\SitebuilderServiceProvider;
use Waterfront\Domain\Ssl\Providers\SslServiceProvider;
use Waterfront\Domain\Subscriptions\Providers\IntelligentCancellationProvider;
use Waterfront\Domain\Transfers\Providers\TransfersServiceProvider;
use Waterfront\Domain\Translations\Providers\TranslationProvider;
use Waterfront\Domain\VPS\Providers\VpsProvider;
use Waterfront\Infra\AcronisClient\Providers\AcronisClientServiceProvider;
use Waterfront\Infra\Basekit\Providers\BasekitClientServiceProvider;
use Waterfront\Infra\CaddyClient\Providers\CaddyClientServiceProvider;
use Waterfront\Infra\DirectAdminClient\Providers\DirectAdminServiceProvider;
use Waterfront\Infra\GandiClient\Providers\GandiClientServiceProvider;
use Waterfront\Infra\HubspotClient\Providers\HubspotClientProvider;
use Waterfront\Infra\Microsoft\Graph\Provider\MicrosoftGraphClientServiceProvider;
use Waterfront\Infra\MicrosoftOnlineClient\Providers\MicrosoftOnlineClientServiceProvider;
use Waterfront\Infra\MollieClient\Providers\MollieClientServiceProvider;
use Waterfront\Infra\News\Providers\NewsServiceProvider;
use Waterfront\Infra\OpenproviderClient\Providers\OpenproviderClientProvider;
use Waterfront\Infra\PaytClient\Providers\PaytClientServiceProvider;
use Waterfront\Infra\PleskClient\Providers\PleskClientProvider;
use Waterfront\Infra\PowerDnsClient\Providers\PowerDnsClientServiceProvider;
use Waterfront\Infra\PuzzelClient\Providers\PuzzelClientServiceProvider;
use Waterfront\Infra\Queue\Providers\HarborQueueProvider;
use Waterfront\Infra\RtrClient\Providers\RtrServiceProvider;
use Waterfront\Infra\SaloonClient\Providers\SaloonClientServiceProvider;
use Waterfront\Infra\SpamExpertsClient\Providers\SpamExpertsServiceProvider;
use Waterfront\Infra\Translation\Providers\TranslationServiceProvider;
use Waterfront\Support\Providers\AppEventServiceProvider;
use Waterfront\Support\Providers\AppServiceProvider;
use Waterfront\Support\Providers\EventServiceProvider;
use Waterfront\Support\Providers\HealthzRouteProvider;
use Waterfront\Support\Providers\HorizonServiceProvider;
use Waterfront\Support\Providers\HttpLogServiceProvider;
use Waterfront\Support\Providers\RouteServiceProvider;
use Waterfront\Support\Providers\SubdomainServiceProvider;
use Waterfront\Support\Providers\VatApiProvider;

return [
    //Basic information
    'name' => Env::get('APP_NAME'),
    'env' => Env::get('APP_ENV', 'production'),
    'tenant' => Env::get('SANDWAVE_TENANT'),
    'tenant_name' => Env::get('SANDWAVE_TENANT_NAME'),
    'tenant_env' => Env::get('SANDWAVE_TENANT_ENV'),
    'schedule_allowed_to_run' => Env::get('SCHEDULE_ALLOWED_TO_RUN', false),
    'theme' => Env::get('APP_THEME', 'seaside'),
    'build_version' => Env::get('BUILD_VERSION', 'undefined'),
    'seed_with_logic' => Env::get('SEED_WITH_LOGIC'),

    // Hostname needs to be without protocol since the hosting clients prepend https:// etc.
    'mock_hostname' => Env::get('MOCK_HOSTNAME'),

    // Crypto key ssl uses to produce encrypted private certificates!.
    'ssl_crypto' => Env::get('CRYPTO_KEY'),

    //debugging
    'debug' => Env::get('APP_DEBUG', false),
    'debug_tinker' => Env::get('APP_DEBUG_TINKER', false),

    'url'               => Env::get('APP_URL', 'http://localhost:8080'),
    'url_partner'       => Env::get('APP_URL_PARTNER', 'http://localhost:8080'),
    'url_partner_api'   => Env::get('PARTNER_API_DOMAIN', 'https://api.sandwaveio.dev'),
    'url_webhook'       => Env::get('APP_URL_WEBHOOK', 'https://localhost:8080'),
    'asset_url' => Env::get('ASSET_URL'),

    //Time & locales
    'timezone' => 'Europe/Amsterdam',
    'locale' => 'nl',
    'fallback_locale' => 'nl',
    'faker_locale'    => 'nl_NL',

    //Encryption
    'key' => Env::get('APP_KEY'),
    'hydra' => [
        'client_id' => Env::get('HYDRA_CLIENT_ID'),
        'secret' => Env::get('HYDRA_CLIENT_SECRET'),
    ],
    'cipher' => 'AES-256-CBC',

    'storefront' => [
        'webhook_hydra_client_id' => Env::get('WEBHOOK_STOREFRONT_HYDRA_CLIENT_ID'),
        'webhook_hydra_client_secret' => Env::get('WEBHOOK_STOREFRONT_HYDRA_CLIENT_SECRET'),
    ],

    //Autoloaded services
    'providers' => [
        // Laravel Framework Service Providers...
        AuthServiceProvider::class,
        BusServiceProvider::class,
        CacheServiceProvider::class,
        ConsoleSupportServiceProvider::class,
        CookieServiceProvider::class,
        DatabaseServiceProvider::class,
        EncryptionServiceProvider::class,
        FilesystemServiceProvider::class,
        FoundationServiceProvider::class,
        HashServiceProvider::class,
        MailServiceProvider::class,
        NotificationServiceProvider::class,
        PaginationServiceProvider::class,
        PipelineServiceProvider::class,
        QueueServiceProvider::class,
        RedisServiceProvider::class,
        PasswordResetServiceProvider::class,
        SessionServiceProvider::class,
        ValidationServiceProvider::class,
        ViewServiceProvider::class,

        // Waterfront Support Providers
        AppEventServiceProvider::class,
        AppServiceProvider::class,
        EventServiceProvider::class,
        HorizonServiceProvider::class,
        Waterfront\Support\Providers\MailServiceProvider::class,
        RouteServiceProvider::class,
        SubdomainServiceProvider::class,
        VatApiProvider::class,
        HealthzRouteProvider::class,

        // Waterfront Infra Providers
        DirectAdminServiceProvider::class,
        HubspotClientProvider::class,
        MollieClientServiceProvider::class,
        NewsServiceProvider::class,
        OpenproviderClientProvider::class,
        PaytClientServiceProvider::class,
        PleskClientProvider::class,
        PowerDnsClientServiceProvider::class,
        HarborQueueProvider::class,
        Waterfront\Infra\RtrClient\Providers\EventServiceProvider::class,
        RtrServiceProvider::class,
        SpamExpertsServiceProvider::class,
        TranslationServiceProvider::class,
        GandiClientServiceProvider::class,
        MicrosoftGraphClientServiceProvider::class,
        MicrosoftOnlineClientServiceProvider::class,
        HttpLogServiceProvider::class,
        BasekitClientServiceProvider::class,
        AcronisClientServiceProvider::class,
        PuzzelClientServiceProvider::class,
        CaddyClientServiceProvider::class,
        SaloonClientServiceProvider::class,

        // Waterfront App Providers
        Waterfront\Apps\API\Atlantis\Providers\RouteServiceProvider::class,
        CompassServiceProvider::class,
        FerryServiceProvider::class,
        HarborServiceProvider::class,
        PartnersServiceProvider::class,
        Waterfront\Apps\API\Partners\Providers\RouteServiceProvider::class,
        NovaServiceProvider::class,
        WebhooksServiceProvider::class,

        // Waterfront Domain Providers
        DnsServiceProvider::class,
        DomainServiceProvider::class,
        FerryDomainServiceProvider::class,
        RedirectsProvider::class,
        Waterfront\Domain\Harbor\Providers\HarborServiceProvider::class,
        DirectAdminHostingServiceProvider::class,
        HostingServiceProvider::class,
        MailManagementServiceProvider::class,
        ManualProvisioningServiceProvider::class,
        Microsoft365Provider::class,
        PaymentServiceServiceProvider::class,
        ProductListUpdaterProvider::class,
        SitebuilderServiceProvider::class,
        SslServiceProvider::class,
        TransfersServiceProvider::class,
        TranslationProvider::class,
        VpsProvider::class,
        IntelligentCancellationProvider::class,

        // Module Service Providers
        LighthouseServiceProvider::class,
    ],

    // Class aliasses, registered when app started, these are lazy loaded they dont hinder
    'aliases' => Facade::defaultAliases()->toArray(),
];
