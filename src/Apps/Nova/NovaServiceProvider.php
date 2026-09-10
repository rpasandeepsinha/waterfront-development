<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\ActionResource;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Resource;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Symfony\Component\Finder\Finder;
use Waterfront\Apps\Nova\Acronis\Resources\NovaAcronisProviderResource;
use Waterfront\Apps\Nova\Customers\Policies\NovaCustomerPolicy;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerWalletResource;
use Waterfront\Apps\Nova\Domains\Resources\NovaDomainContactAnonymousHandleResource;
use Waterfront\Apps\Nova\Domains\Resources\NovaDomainSubscriptionResource;
use Waterfront\Apps\Nova\Domains\Resources\NovaOpenproviderProviderCredentials;
use Waterfront\Apps\Nova\Domains\Resources\NovaOpenSrsProviderCredentials;
use Waterfront\Apps\Nova\Domains\Resources\NovaRtrProviderCredentials;
use Waterfront\Apps\Nova\General\Dashboards\NovaMainDashboard;
use Waterfront\Apps\Nova\General\Dashboards\NovaStatisticsDashboard;
use Waterfront\Apps\Nova\Hosting\Resources\NovaRedirectLegacyServerResource;
use Waterfront\Apps\Nova\Hosting\Resources\NovaServerResource;
use Waterfront\Apps\Nova\Hosting\Resources\NovaSpamExpertsClusterResource;
use Waterfront\Apps\Nova\Invoices\Resources\NovaInvoiceResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365CustomerResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365DeploymentResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365KpnProductsResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365LogsResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365SyncLogsResource;
use Waterfront\Apps\Nova\Migrations\Resources\NovaFerryInternalNameserverResource;
use Waterfront\Apps\Nova\Migrations\Resources\NovaMigrationStateResource;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptResource;
use Waterfront\Apps\Nova\OneTimeServices\Resources\NovaOneTimeServiceResource;
use Waterfront\Apps\Nova\Orders\Resources\NovaOrderResource;
use Waterfront\Apps\Nova\Products\Policies\NovaProductPolicy;
use Waterfront\Apps\Nova\Products\Resources\NovaIntroductionPricingResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductDiscountResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductGroupResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Policies\NovaSubscriptionPolicy;
use Waterfront\Apps\Nova\Subscriptions\Policies\NovaTransferPolicy;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaFreeSubscriptionResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Apps\Nova\VPS\Policies\NovaVirtualMachineDeploymentPolicy;
use Waterfront\Apps\Nova\VPS\Resources\NovaCloudStackEnvironmentProductResource;
use Waterfront\Apps\Nova\VPS\Resources\NovaCloudStackEnvironmentResource;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Translation\Translator;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [
        Transfer::class => NovaTransferPolicy::class,
        Product::class => NovaProductPolicy::class,
        Subscription::class => NovaSubscriptionPolicy::class,
        Customer::class => NovaCustomerPolicy::class,
        VirtualMachineDeployment::class => NovaVirtualMachineDeploymentPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Fake that we have a customer, authentication is checked in middleware after registering Nova.
        if (! $this->app->runningUnitTests() && ! $this->app->runningInConsole()) {
            Auth::guard()->setUser($this->getFakeCustomer());
        }

        $urlGenerator = $this->app->make(UrlGenerator::class);

        Nova::style('searchbar', $urlGenerator->asset('sandwaveio/searchbar.css'));
        Nova::style('loading', $urlGenerator->asset('sandwaveio/loading.css'));
        Nova::script('loading', $urlGenerator->asset('sandwaveio/loading.js'));

        Nova::withBreadcrumbs();

        $this->buildMainMenu();
        $this->buildUserMenu();

        Nova::withoutNotificationCenter();
    }

    public function register(): void
    {
        parent::register();
        $this->registerNovaPolicies();

        // Nova uses its own internal exception handler instead of using the default. So we have to send them
        // to Sentry separately.
        Nova::report(function ($exception) {
            if ($this->app->bound('sentry')) {
                $this->app->make('sentry')->captureException($exception);
            }

            $logger = resolve(LoggerInterface::class);
            $logger->error(
                sprintf('Uncaught Nova exception: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
        });
    }

    #[Override]
    protected function registerExceptionHandler(): void
    {
        $this->app->bind(ExceptionHandler::class, NovaCustomExceptionHandler::class);
    }

    #[Override]
    protected function authorization(): void
    {
        Nova::auth(fn ($request) => true);
    }

    protected function routes(): void
    {
        Nova::routes()->register();
    }

    /**
     * @return array<mixed>
     */
    protected function dashboards(): array
    {
        return [
            new NovaMainDashboard(),
            new NovaStatisticsDashboard(),
        ];
    }

    // Because Nova is located in the /src folder and default nova in located in the /app folder we have to overwrite the resources function of Nova to get it to work.
    protected function resources(): void
    {
        $namespace = 'Waterfront\\Apps\\Nova\\';

        $resources = [];

        foreach (new Finder()->in('../src/Apps/Nova')->files() as $resource) {
            $resource = $namespace . str_replace(
                ['../src/Apps/Nova/', '/', '.php'],
                ['', '\\', ''],
                $resource->getPathname()
            );

            if (
                is_subclass_of($resource, Resource::class) &&
                ! new ReflectionClass($resource)->isAbstract() &&
                ! is_subclass_of($resource, ActionResource::class)
            ) {
                $resources[] = $resource;
            }
        }

        Nova::resources(
            $resources
        );
    }

    private function registerNovaPolicies(): void
    {
        foreach ($this->policies as $key => $value) {
            Gate::policy($key, $value);
        }
    }

    private function getFakeCustomer(): Customer
    {
        return new Customer([
            'uuid'                    => Uuid::uuid4(), // In the case that tests disable events this is necessary.
            'customer_number'         => 12345,
            'organization'            => 'Fake org',
            'department'              => 'Depart',
            'first_name'              => 'Piet',
            'last_name'               => 'Puk',
            'gender'                  => Gender::MALE->value,
            'invoice_history_url'     => 'https://sandwaveio.dev',
            'admin_url'               => 'https://sandwaveio.dev',
            'phone_country_code'      => '31',
            'phone_area_code'         => '6',
            'phone_subscriber_number' => '87281426',
            'email'                   => 'fake@sandwaveio.dev',
            'locale'                  => Locale::DUTCH->value,
            'terms_of_payment'        => 14,
            'is_verified'            => true,
            'payment_type'            => PaymentType::CREDIT,
            'terms_accepted'          => true,
            'created_at'              => CarbonImmutable::now(),
            'updated_at'              => CarbonImmutable::now(),
            'anonymized_at'           => null,
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    private function buildMainMenu(): void
    {
        $translator = $this->app->make(Translator::class);

        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = $this->app->make(AuthorizationChecker::class);

        Nova::mainMenu(fn (Request $request) => [
            MenuSection::dashboard(NovaMainDashboard::class)->icon('home'),

            MenuGroup::make($translator->translate('nova-group.core'), [
                MenuItem::resource(NovaSubscriptionResource::class),
                MenuItem::resource(NovaCustomerResource::class),
                MenuItem::resource(NovaOneTimeServiceResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova-group.products'), [
                MenuItem::resource(NovaProductGroupResource::class),
                MenuItem::resource(NovaIntroductionPricingResource::class),
                MenuItem::resource(NovaProductResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova-group.invoices'), [
                MenuItem::resource(NovaOrderResource::class),
                MenuItem::resource(NovaInvoiceResource::class),
                MenuItem::resource(NovaProductDiscountResource::class),
                MenuItem::resource(NovaCustomerWalletResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova-group.microsoft365'), [
                MenuItem::resource(NovaMicrosoft365DeploymentResource::class),
                MenuItem::resource(NovaMicrosoft365KpnProductsResource::class),
                MenuItem::resource(NovaMicrosoft365CustomerResource::class),
                MenuItem::resource(NovaMicrosoft365SyncLogsResource::class),
                MenuItem::resource(NovaMicrosoft365LogsResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova-group.system'), [
                MenuItem::resource(NovaCloudStackEnvironmentProductResource::class),
                MenuItem::resource(NovaCloudStackEnvironmentResource::class),
                MenuItem::resource(NovaServerResource::class),
                MenuItem::resource(NovaDomainContactAnonymousHandleResource::class),
                MenuItem::resource(NovaDomainSubscriptionResource::class),
                MenuItem::resource(NovaAcronisProviderResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova-group.statistics'), [
                MenuItem::resource(NovaOneOffScriptResource::class)
                    ->canSee(fn (Request $request) => $authorizationChecker->can(Permissions::RUN_ONE_OFF_SCRIPT)),
                MenuItem::dashboard(NovaStatisticsDashboard::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova.group.misc-subscriptions-views'), [
                MenuItem::resource(NovaFreeSubscriptionResource::class),
            ])->collapsable(),

            MenuGroup::make($translator->translate('nova.group.legacy-business'), [
                MenuItem::resource(NovaMigrationStateResource::class),
                MenuItem::resource(NovaFerryInternalNameserverResource::class),
                MenuItem::resource(NovaRtrProviderCredentials::class),
                MenuItem::resource(NovaOpenproviderProviderCredentials::class),
                MenuItem::resource(NovaOpenSrsProviderCredentials::class),
                MenuItem::resource(NovaRedirectLegacyServerResource::class),
                MenuItem::resource(NovaSpamExpertsClusterResource::class),
            ])->collapsable(),
        ]);
    }

    private function buildUserMenu(): void
    {
        /*
         * Caching the config is an artisan command. So we can't use our own configuration class here,
         * since it throws an exception when the value is not set yet in the cached config.
         * Related: https://yh-jira.atlassian.net/browse/WATER-6054
         */
        $compassUrl = config('nova.compass_url');
        $settingsUrl = config('lighthouse.settings_url');
        $logoutUrl = config('lighthouse.logout_url');
        assert(is_string($compassUrl));
        assert(is_string($settingsUrl));
        assert(is_string($logoutUrl));

        Nova::userMenu(
            fn (Request $request, Menu $menu) => $menu
            ->append(MenuItem::externalLink('Compass', $compassUrl))
            ->append(MenuItem::externalLink('Account / settings', $settingsUrl))
            ->append(MenuItem::externalLink('Account / logout', $logoutUrl))
        );
    }
}
