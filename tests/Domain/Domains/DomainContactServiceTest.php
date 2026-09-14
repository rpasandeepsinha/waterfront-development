<?php

declare(strict_types=1);

namespace Tests\Domain\Domains;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Services\DomainContactService;

#[CoversClass(DomainContactService::class)]
class DomainContactServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private DomainContactService $domainContactService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $this->domainContactService = $this->app->make(DomainContactService::class);
    }

    #[Test]
    public function customerCanOnlyHaveOneDefaultOwner(): void
    {
        $contact1 = new DomainContactFactory()->for($this->customer)->createOne();
        $contact2 = new DomainContactFactory()->for($this->customer)->createOne();

        self::assertCount(1, array_filter([$contact1->default_owner, $contact2->default_owner]));
    }

    #[Test]
    public function defaultDomainContact(): void
    {
        new DomainContactFactory()->for($this->customer)->createOne([
            'first_name' => 'fakefirstname123',
        ]);

        $contact = $this->domainContactService->findOrCreateDefaultOwner($this->customer);

        self::assertSame(
            'fakefirstname123',
            $contact->first_name,
            'First name does not match the default domain contact.',
        );
    }

    #[Test]
    public function findOrCreateDefaultOwner(): void
    {
        $domainContact = $this->domainContactService->findOrCreateDefaultOwner($this->customer);

        self::assertSame($this->customer->phone_subscriber_number, $domainContact->phone_subscriber_number);
        self::assertSame($this->customer->phone_area_code, $domainContact->phone_area_code);
        self::assertSame($this->customer->phone_country_code, $domainContact->phone_country_code);
        self::assertSame('NL', $domainContact->country_code);

        self::assertDatabaseHas('domain_contacts', [
            'customer_id' => $this->customer->id,
            'country_code' => 'NL',
            'phone_country_code' => $this->customer->phone_country_code,
            'phone_area_code' => $this->customer->phone_area_code,
            'phone_subscriber_number' => $this->customer->phone_subscriber_number,
        ]);
    }

    #[Test]
    public function createContactOwnerFromRemoteCustomerContactCreatesAndAssociatesContactOwner(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne();

        $deployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $remoteCustomerContact = new RetrieveCustomerResponse(
            status: null,
            reason: null,
            responseCode: 0,
            handle: 'REGISTRANT',
            organization: 'Acme',
            vat: 'NL000000000B01',
            firstName: 'John',
            lastName: 'Doe',
            gender: 'M',
            phone: '+31612345678',
            email: 'john.doe@example.com',
            streetName: 'Street',
            streetNumber: '1',
            zip: '1234AB',
            city: 'Amsterdam',
            countryCode: 'NL',
        );

        $this->domainContactService->createContactOwnerFromRemoteCustomerContact($deployment, $remoteCustomerContact);

        $deployment->refresh();
        self::assertNotNull($deployment->contact_owner_id);
        $contactOwner = $deployment->contactOwner;
        self::assertInstanceOf(DomainContact::class, $contactOwner);
        self::assertSame('john.doe@example.com', $contactOwner->email);
        self::assertSame('John', $contactOwner->first_name);
        self::assertSame('Doe', $contactOwner->last_name);
        self::assertSame('Acme', $contactOwner->organization);
        self::assertSame($this->customer->id, $contactOwner->customer_id);
        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $contactOwner->id,
            'provider_id' => $deployment->provider_id,
            'external_contact' => 'REGISTRANT',
            'domain_business_unit_id' => $deployment->domain_business_unit_id,
        ]);
    }
}
