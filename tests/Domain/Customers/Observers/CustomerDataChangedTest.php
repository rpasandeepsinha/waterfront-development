<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Observers;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerContactFactory;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Events\CustomerDataChangedEvent;
use Waterfront\Domain\Customers\Observers\CustomerObserver;

#[CoversClass(CustomerDataChangedEvent::class)]
#[CoversClass(CustomerObserver::class)]
class CustomerDataChangedTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([CustomerDataChangedEvent::class]);
    }

    #[Test]
    public function customerChanged(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();

        $customer->first_name = 'pietjepuk';
        $customer->update();

        Event::assertDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerAddressChanged(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        $address = new CustomerAddressFactory()->createOneQuietly([
            'customer_id' => $customer->id,
            'country_code' => 'NL',
        ]);

        $address->country_code = 'BE';
        $address->update();

        Event::assertDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerFinancialContactCreated(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        new CustomerContactFactory()->create([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::FINANCIAL->value,
        ]);

        Event::assertDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerFinancialContactUpdated(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        $contact = new CustomerContactFactory()->createOneQuietly([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::FINANCIAL->value,
            'uuid' => '00000000-0000-0000-0000-000000000001',
        ]);

        $contact->first_name = 'pietjepuk';
        $contact->update();

        Event::assertDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerFinancialContactDeleted(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        $contact = new CustomerContactFactory()->createOneQuietly([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::FINANCIAL->value,
            'uuid' => '00000000-0000-0000-0000-000000000001',
        ]);

        $contact->delete();

        Event::assertDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerNonFinancialContactCreated(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        new CustomerContactFactory()->create([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::TECHNICAL->value,
        ]);

        Event::assertNotDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerNonFinancialContactUpdated(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        $contact = new CustomerContactFactory()->createOneQuietly([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::TECHNICAL->value,
            'uuid' => '00000000-0000-0000-0000-000000000001',
        ]);

        $contact->first_name = 'pietjepuk';
        $contact->update();

        Event::assertNotDispatched(CustomerDataChangedEvent::class);
    }

    #[Test]
    public function customerNonFinancialContactDeleted(): void
    {
        $customer = new CustomerFactory()->createOneQuietly();
        $contact = new CustomerContactFactory()->createOneQuietly([
            'customer_id' => $customer->id,
            'type' => CustomerContactType::TECHNICAL->value,
            'uuid' => '00000000-0000-0000-0000-000000000001',
        ]);

        $contact->delete();

        Event::assertNotDispatched(CustomerDataChangedEvent::class);
    }
}
