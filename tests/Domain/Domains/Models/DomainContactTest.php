<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactAnonymousHandleFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

#[CoversClass(DomainContact::class)]
class DomainContactTest extends IntegrationTestCase
{
    private Customer $customer;

    private Provider $domainProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne([
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
        ]);

        $this->domainProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);
    }

    #[Test]
    public function verifyDomainContactIsAnonymous(): void
    {
        $anonymousHandle = '25B-BUNAME-anonymous';
        $publicHandle = '25C-BUNAME-public';
        DomainContactAnonymousHandleFactory::new()
            ->create(['handle' => $anonymousHandle]);

        $anonymizedContact = DomainContactFactory::new()
            ->for($this->customer)
            ->createOne();
        $anonymizedContact->providers()->attach(
            $this->domainProvider,
            [
                'external_contact' => $anonymousHandle,
            ]
        );

        $publicContact = DomainContactFactory::new()
            ->for($this->customer)
            ->createOne();
        $publicContact->providers()->attach(
            $this->domainProvider,
            [
                'external_contact' => $publicHandle,
            ]
        );

        self::assertTrue($anonymizedContact->has_anonymous_handle);
        self::assertFalse($publicContact->has_anonymous_handle);
    }
}
