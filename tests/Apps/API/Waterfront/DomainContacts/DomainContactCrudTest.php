<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DomainContacts;

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

#[CoversNothing]
class DomainContactCrudTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function index(): void
    {
        new DomainContactFactory()->createOne([
            'email' => 'fake@faker.nl',
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-contact.contacts.index'),
            )
            ->assertOk();

        $json = $response->json();
        assert(is_array($json));
        $domainContacts = $json['data'];

        self::assertCount(1, $domainContacts);
        self::assertSame('fake@faker.nl', $domainContacts[0]['email']);
    }

    #[Test]
    public function show(): void
    {
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        $contact = new DomainContactFactory()->createOne([
            'email' => 'fake@faker.nl',
            'customer_id' => $this->customer->id,
        ]);

        $contact->providers()->attach($provider, [
            'external_contact' => 'test_external',
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'name' => 'extension',
            'slug' => 'extension',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => 'ketchup.nl',
            'contract_period' => 24,
            'gross_price' => 1008,
            'net_price' => 585,
            'product_uuid' => $product->uuid,
        ]);

        $domainDeployment = new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $contact->contactOwnerDomainSubscriptions()->save($domainDeployment);
        self::assertNotNull($domainDeployment->contact_owner_id);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-contact.contacts.show', ['contact' => $contact->id]),
            )
            ->assertOk();

        $json = $response->json();
        assert(is_array($json));
        $data = $json['data'];

        self::assertSame('fake@faker.nl', $data['email']);
    }

    #[Test]
    public function showNotFound(): void
    {
        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-contact.contacts.show', ['contact' => $contact->id . 22]),
            )
            ->assertNotFound();
    }

    #[Test]
    public function store(): void
    {
        $payload = json_decode(
            (string) file_get_contents(__DIR__ . '/data/domain_contact.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.store', $payload),
            )
            ->assertCreated();

        self::assertDatabaseHas('domain_contacts', [
            'customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function storeInvalid(): void
    {
        $payload = json_decode(
            (string) file_get_contents(__DIR__ . '/data/domain_contact_invalid.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.store', $payload),
            )
            ->assertUnprocessable();

        self::assertDatabaseMissing('domain_contacts', [
            'email' => 'developer@sandwave.io',
        ]);
    }

    #[Test]
    public function destroyDomainContactRoute(): void
    {
        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.domain-contact.contacts.destroy', ['contact' => $contact->id]),
            )
            ->assertOk();

        self::assertDatabaseHas('domain_contacts', [
            'customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function setDefaultSuccess(): void
    {
        $contact = new DomainContactFactory()->createOne([
            'default_owner' => 0,
            'customer_id' => $this->customer->id,
        ]);

        $contact2 = new DomainContactFactory()->createOne([
            'default_owner' => 1,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.domain-contact.contacts.set_default', ['contact' => $contact->id]),
            )
            ->assertOk();

        self::assertDatabaseHas('domain_contacts', [
            'id' => $contact->id,
            'default_owner' => 1,
        ]);
        self::assertDatabaseHas('domain_contacts', [
            'id' => $contact2->id,
            'default_owner' => 0,
        ]);
    }

    #[Test]
    public function setDefaultWithAnonymousContactFails(): void
    {
        $anonymousContact = new DomainContactFactory()->createOne([
            'default_owner' => 0,
            'customer_id' => $this->customer->id,
        ]);

        $anonymousHandleIdentifier = 'anonymized_handle';

        $rtrProvider = ProviderFactory::new()->create([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $anonymousContact->providers()->attach(
            $rtrProvider,
            ['external_contact' => $anonymousHandleIdentifier],
        );

        DomainContactAnonymousHandleFactory::new()->create([
            'handle' => $anonymousHandleIdentifier,
        ]);

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.domain-contact.contacts.set_default', [
                    'contact' => $anonymousContact->id,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function destroyDomainContact(): void
    {
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => 'extension',
            'slug' => 'extension',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => 'ketchup.nl',
            'contract_period' => 24,
            'gross_price' => 1008,
            'net_price' => 585,
            'product_uuid' => $product->uuid,
        ]);

        $domainDeployment = new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $contact->providers()->attach($provider, [
            'external_contact' => 'test_external',
        ]);

        $contactId = $contact->refresh()->id;

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.domain-contact.contacts.destroy', ['contact' => $contact->id]),
            )
            ->assertOk();

        // ASSERTIONS
        self::assertDatabaseMissing('domain_contacts', [
            'id' => $contactId,
            'deleted_at' => null,
        ]);

        self::assertDatabaseMissing('domain_contact_provider', [
            'provider_id' => $provider->id,
            'domain_contact_id' => $contactId,
            'deleted_at' => null,
        ]);

        self::assertDatabaseMissing('domain_deployments', [
            'contact_owner_id' => $contactId,
        ]);

        // These should NOT be deleted
        self::assertDatabaseHas('customers', [
            'id' => $this->customer->id,
        ]);
        self::assertDatabaseHas('product_groups', [
            'id' => $productGroup->id,
        ]);
        self::assertDatabaseHas('domain_deployments', [
            'id' => $domainDeployment->id,
        ]);
        self::assertDatabaseHas('providers', [
            'id' => $provider->id,
            'type' => ProviderType::DOMAIN,
        ]);
        self::assertNotNull(
            DomainContact::onlyTrashed()->where('id', $contactId)->first(),
            'Contact was not soft deleted but force deleted',
        );
    }
}
