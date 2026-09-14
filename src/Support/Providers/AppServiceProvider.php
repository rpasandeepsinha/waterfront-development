<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\UserResolver;
use OwenIt\Auditing\Models\Audit as AuditBaseModel;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SandwaveIo\LighthouseAuthBase\Service\IdentitySchemaConverter;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedEmployee;
use Waterfront\Domain\AuditLogs\Observers\AuditObserver;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Observers\CustomerAddressObserver;
use Waterfront\Domain\Customers\Observers\CustomerContactObserver;
use Waterfront\Domain\Customers\Observers\CustomerObserver;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\ManualProvisioning\Observers\ManualSubscriptionObserver;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Placeholder\Observers\PlaceholderObserver;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPromotion;
use Waterfront\Domain\Products\Observers\ProductGroupObserver;
use Waterfront\Domain\Products\Observers\ProductObserver;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Observers\SslDeploymentObserver;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Observers\SubscriptionObserver;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\IdentitySerializerProxy;
use Waterfront\Infra\Authentication\OathKeeperService;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\Configuration;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\Translator;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Config\ApplicationConfig;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\Tenant;

class AppServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        Audit::observe(AuditObserver::class);
        AuditBaseModel::observe(AuditObserver::class);
        Subscription::observe([
            SubscriptionObserver::class,
            ManualSubscriptionObserver::class,
            PlaceholderObserver::class,
        ]);
        Customer::observe(CustomerObserver::class);
        CustomerContact::observe(CustomerContactObserver::class);
        CustomerAddress::observe(CustomerAddressObserver::class);
        Product::observe(ProductObserver::class);
        ProductGroup::observe(ProductGroupObserver::class);
        SslDeployment::observe(SslDeploymentObserver::class);

        //Morphmap required for morph relations.
        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap =
            [
                // Customer related
                Customer::class => Customer::class,
                CustomerAddress::class => CustomerAddress::class,
                CustomerContact::class => CustomerContact::class,

                // 'Parent' Subscription related
                Subscription::class => Subscription::class,

                //'Child' subscription related
                DomainDeployment::class => DomainDeployment::class,
                HostingDeployment::class => HostingDeployment::class,
                ResellerHostingDeployment::class => ResellerHostingDeployment::class,
                SslDeployment::class => SslDeployment::class,
                Microsoft365Deployment::class => Microsoft365Deployment::class,
                ManagerDomainDeployment::class => ManagerDomainDeployment::class,
                VirtualMachineDeployment::class => VirtualMachineDeployment::class,
                VolumeDeployment::class => VolumeDeployment::class,

                //Product related
                ProductPromotion::class => ProductPromotion::class,
            ] + $this->getOldMorphMapArray();
        Relation::morphMap($morphMap);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        Date::use(CarbonImmutable::class);

        $this->app->bind(UserResolver::class, \OwenIt\Auditing\Resolvers\UserResolver::class);
        $this->app->bind(TranslatorInterface::class, Translator::class);
        $this->app->bind(ConfigurationInterface::class, Configuration::class);

        $configuration = $this->resolve(ConfigurationInterface::class);

        $this->app->singleton(
            PublicSuffixList::class,
            fn () => new PublicSuffixList($configuration, ! $this->app->runningUnitTests()),
        );

        CarbonImmutable::setLocale($configuration->getAsString('app.locale'));

        if ($configuration->getAsBoolean('database.query_logging') === true) {
            $logger = $this->resolve(LoggerInterface::class);

            DB::listen(function (QueryExecuted $query) use ($logger): void {
                $logger->debug(
                    $query->sql,
                    [
                        LoggingContextKeys::META => [
                            'connection' => $query->connectionName,
                            'bindings' => $query->bindings,
                            'time' => $query->time,
                        ],
                    ],
                );
            });
        }

        $this->app->singleton(IdentitySchemaConverter::class, fn () => new IdentitySchemaConverter(
            new IdentitySerializerProxy(),
        ));

        $this->app->scoped(AuthenticationManager::class, fn () => new AuthenticationManager(
            $this->resolve(OathKeeperService::class),
            $this->resolve(LoggerInterface::class),
            $this->resolve(AuthManager::class),
            $this->resolve(IdentitySchemaConverter::class),
            $this->resolve(CustomerRepository::class),
            $configuration->getAsString('auth.console_identity_uuid'),
        ));

        $environment = Environment::from($configuration->getAsString('app.tenant_env'));
        $tenant = Tenant::from($configuration->getAsString('app.tenant_name'));
        $this->app->bind(Environment::class, fn () => $environment);
        $this->app->bind(Tenant::class, fn () => $tenant);

        $this->app->bind(RequireAuthenticatedEmployee::class, fn () => new RequireAuthenticatedEmployee(
            $this->resolve(AuthenticationManager::class),
            $this->resolve(LoggerInterface::class),
            $configuration->getAsString('lighthouse.logout_url'),
            $this->resolve(Environment::class),
        ));

        $this->app->bind(JwtAuthentication::class, fn () => new JwtAuthentication(
            $this->resolve(AuthenticationManager::class),
            $this->resolve(LoggerInterface::class),
            $configuration->getAsString('lighthouse.logout_url'),
        ));

        $this->registerConfiguration($configuration);
    }

    /**
     * Loading all required configuration for the whole application. If a value doesn't exist or can't be parsed
     * into the application config the application will exit.
     */
    private function registerConfiguration(ConfigurationInterface $configuration): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'customershared',
        );

        $defaultTaxRate = $configuration->getAsInteger('customershared.default_tax_rate');
        $applicationConfig = new ApplicationConfig($defaultTaxRate);

        $this->app->singleton(ApplicationConfig::class, fn () => $applicationConfig);
    }

    /**
     * This is a list of morphs for auditable classes that have been moved at
     * some point. This must be updated whenever an auditable class is moved.
     *
     * @return array<string, string>
     */
    private function getOldMorphMapArray(): array
    {
        return [
            'App\Models\User' => 'App\Models\User',
            'App\Models\Customer' => Customer::class,
            'App\Models\CustomerAddress' => CustomerAddress::class,
            'App\Models\CustomerContact' => CustomerContact::class,
            'App\Models\HostingChange' => SubscriptionChange::class,
            'App\Models\HostingDeployment' => HostingDeployment::class,
            'App\Models\Order' => Order::class,
            'App\Models\OrderLineItem' => OrderLineItem::class,
            'App\Models\ResellerHostingSubscription' => ResellerHostingDeployment::class,
            'Waterfront\Domain\ResellerHosting\Models\ResellerHostingSubscription' => ResellerHostingDeployment::class,
            'App\Models\Subscription' => Subscription::class,
            'Modules\Customer\Models\Customer' => Customer::class,
            'Modules\Customer\Models\CustomerAddress' => CustomerAddress::class,
            'Modules\Customer\Models\CustomerContact' => CustomerContact::class,
            'Modules\DnsService\Models\DnsCustomerTemplate' => DnsCustomerTemplate::class,
            'Modules\DnsService\Models\DnsCustomerTemplateRecord' => DnsCustomerTemplateRecord::class,
            'Modules\DomainService\Models\DomainContact' => DomainContact::class,
            'Modules\DomainService\Models\Subscription' => DomainDeployment::class,
            'Modules\HostingService\Models\Subscription' => HostingDeployment::class,
            'Modules\PaymentService\Models\Payment' => Payment::class,
            'Modules\SslService\Models\SslSubscription' => SslDeployment::class,
            'Modules\SslService\Models\Subscription' => SslDeployment::class,
            'Modules\Voucher\Models\Voucher' => Voucher::class,
            'Waterfront\Domain\Domains\Models\DomainSubscription' => DomainDeployment::class,
            'Waterfront\Domain\Ssl\Models\SslSubscription' => SslDeployment::class,
            'Waterfront\Domain\CloudStack\Models\ManagerDomainSubscription' => ManagerDomainDeployment::class,
            'Waterfront\Domain\CloudStack\Models\VirtualMachineSubscription' => VirtualMachineDeployment::class,
            'Waterfront\Domain\CloudStack\Models\VolumeSubscription' => VolumeDeployment::class,
            'Waterfront\Domain\VPS\Models\ManagerDomainSubscription' => ManagerDomainDeployment::class,
            'Waterfront\Domain\VPS\Models\VirtualMachineSubscription' => VirtualMachineDeployment::class,
            'Waterfront\Domain\VPS\Models\VolumeSubscription' => VolumeDeployment::class,
            'Waterfront\Domain\Hosting\Models\HostingSubscription' => HostingDeployment::class,
        ];
    }
}
