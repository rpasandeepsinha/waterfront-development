<?php

declare(strict_types=1);

namespace Tests\DataProvider;

use Faker\Factory as Faker;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DomainSubscriptionDataProvider
{
    public static function subscription(
        ?string $domain = null,
        ?Customer $customer = null,
    ): Subscription {
        $filledProductGroup = new ProductGroupFactory()->extension()->createOne();
        $filledProduct = new ProductFactory()->for($filledProductGroup)->createOne();
        new ProductPriceComponentFactory()
            ->for($filledProduct)
            ->registration()
            ->createOne();
        $filledCustomer = $customer ?? new CustomerFactory()->createOne();
        $filledDomain = $domain ?? Faker::create()->domainName();

        return new SubscriptionFactory()
            ->for($filledCustomer)
            ->for($filledProduct)
            ->forDomain($filledDomain)
            ->createOne();
    }

    public static function deployment(
        ?Subscription $subscription = null,
        ?Provider $domainProvider = null,
    ): DomainDeployment {
        $filledSubscription = $subscription ?? self::subscription();
        $filledDomainProvider = $domainProvider ?? ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        $filledDomainContact = new DomainContactFactory()->for($filledSubscription->customer)->createOne();

        return new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $filledSubscription->uuid,
            'provider_id' => $filledDomainProvider->id,
            'contact_owner_id' => $filledDomainContact->id,
        ]);
    }
}
