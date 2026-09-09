<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\Config\ConfigFactory;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\EmailClient;
use Waterfront\Infra\HubspotClient\Serializer\HubspotSerializerFactory;
use Waterfront\Infra\HubspotClient\SubscriptionClient;
use Waterfront\Support\Providers\BaseProvider;

class HubspotClientProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/hubspot.php', 'hubspot');
    }

    public function register(): void
    {
        $this->app->singleton(HubspotConfigDTO::class, fn () => $this->resolve(ConfigFactory::class)->get());

        $this->app->singleton(function (): SubscriptionClient {
            $crm = $this->resolve(HubspotCrmHttpClient::class);
            $config = $this->resolve(HubspotConfigDTO::class);

            return new SubscriptionClient(
                crm: $crm,
                serializer: HubspotSerializerFactory::get(),
                config: $config,
            );
        });

        $this->app->singleton(function (): EmailClient {
            $crm = $this->resolve(HubspotCrmHttpClient::class);

            return new EmailClient(
                crm: $crm,
                serializer: HubspotSerializerFactory::get(),
            );
        });

        $this->app->singleton(function (): ContactsClient {
            $crm = $this->resolve(HubspotCrmHttpClient::class);
            $config = $this->resolve(HubspotConfigDTO::class);

            return new ContactsClient(
                crm: $crm,
                serializer: HubspotSerializerFactory::get(),
                config: $config
            );
        });
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            HubspotCrmHttpClient::class,
            SubscriptionClient::class,
            EmailClient::class,
            ContactsClient::class,
        ];
    }
}
