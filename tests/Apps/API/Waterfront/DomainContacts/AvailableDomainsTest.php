<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DomainContacts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactAnonymousHandleFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversNothing]
class AvailableDomainsTest extends IntegrationTestCase
{
    use WithFaker;

    private Customer $customer;

    private DomainContact $domainContact;

    private DomainContact $anonymousContact;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne();
        $secondCustomer = new CustomerFactory()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'type' => ProviderType::DOMAIN,
            'default' => true,
        ]);

        $rtrProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'extension']);
        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        $this->domainContact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $secondDomainContact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $this->anonymousContact = DomainContactFactory::new()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $anonymousHandleIdentifier = 'anonymized_handle';

        $this->anonymousContact->providers()->attach(
            $rtrProvider,
            ['external_contact' => $anonymousHandleIdentifier]
        );

        DomainContactAnonymousHandleFactory::new()->createOne([
            'handle' => $anonymousHandleIdentifier,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'deletedshouldnotshow.com',
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        $subscription2 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'canceledshouldappear.com',
            'administrative_status' => AdministrativeStatus::CANCELED->value,
        ]);

        $subscription3 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => $this->faker->domainName(),
        ]);

        $subscription4 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => $this->faker->domainName(),
        ]);

        $subscription5 = new SubscriptionFactory()->for($secondCustomer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'shouldntbehere.com',
        ]);

        new SubscriptionFactory()->for($secondCustomer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'xxxxxddddd.com',
        ]);

        $subscription7 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'rmnddesign.com',
        ]);

        $subscription8 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'canceledshouldappearcurrentlyhasanonymouscontact.com',
            'administrative_status' => AdministrativeStatus::CANCELED->value,
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription2->uuid,
        ]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription3->uuid,
            'contact_owner_id' => $this->domainContact->id,
        ]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription4->uuid,
            'contact_owner_id' => $this->domainContact->id,
        ]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription5->uuid,
            'contact_owner_id' => $secondDomainContact->id,
        ]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription7->uuid,
        ]);
        new DomainDeploymentFactory()->for($this->anonymousContact, 'contactOwner')->createOne([
            'provider_id' => $rtrProvider->id,
            'subscription_uuid' => $subscription8->uuid,
        ]);
    }

    #[Test]
    public function showAvailableDomains(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.domain-contact.contacts.available-domains', $this->domainContact))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'domain' => 'canceledshouldappear.com',
                        'linked' => false,
                    ],
                    [
                        'domain' => 'canceledshouldappearcurrentlyhasanonymouscontact.com',
                        'linked' => false,
                    ],
                    [
                        'domain' => 'rmnddesign.com',
                        'linked' => false,
                    ],
                ],
            ]);
    }

    #[Test]
    public function showAvailableDomainsWhenProvidingAnonymousContact(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.domain-contact.contacts.available-domains', $this->anonymousContact))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }
}
