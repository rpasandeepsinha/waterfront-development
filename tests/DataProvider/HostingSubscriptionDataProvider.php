<?php

declare(strict_types=1);

namespace Tests\DataProvider;

use Faker\Factory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingSubscriptionDataProvider
{
    public static function administrativeSubscription(
        ?string $domain = null,
        ?Customer $customer = null,
    ): Subscription {
        $productGroupFilled = new ProductGroupFactory()->hosting()->createOne();
        $filledProduct = new ProductFactory()->for($productGroupFilled)->createOne();
        new ProductPriceComponentFactory()
            ->for($filledProduct)
            ->registration()
            ->createOne();
        $filledCustomer = $customer ?? new CustomerFactory()->createOne();
        $filledDomain = $domain ?? Factory::create()->domainName();

        return new SubscriptionFactory()
            ->for($filledCustomer)
            ->for($filledProduct)
            ->forDomain($filledDomain)
            ->createOne();
    }

    /** Default provider that gets made is an directadmin provider */
    public static function technicalSubscription(
        ?Subscription $subscription = null,
        ?Provider $hostingProvider = null,
    ): HostingDeployment {
        $filledSubscription = $subscription ?? self::administrativeSubscription();
        $filledProvider = $hostingProvider ?? new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $filledServer = new ServerFactory()->createOne();

        return new HostingDeploymentFactory()->for($filledServer)->createOne([
            'subscription_uuid' => $filledSubscription->uuid,
            'provider_id' => $filledProvider->id,
        ]);
    }
}
