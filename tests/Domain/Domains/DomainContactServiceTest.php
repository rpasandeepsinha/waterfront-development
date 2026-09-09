<?php

declare(strict_types=1);

namespace Tests\Domain\Domains;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
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
            'First name does not match the default domain contact.'
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
}
