<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\MailManagement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;

#[CoversNothing]
class MailUsersListTest extends IntegrationTestCase
{
    private Customer $customer;

    private string $domain;

    public function setUp(): void
    {
        parent::setUp();

        $this->domain = 'example.com';
        $this->customer = new CustomerFactory()->createOne();

        $server = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN_MAIL,
            'hostname' => $this->domain,
        ]);
        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'hosting']);
        $product = new ProductFactory()
            ->mailOnly($productGroup)
            ->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne([
                'domain' => 'example.com',
                'contract_period' => 12,
                'gross_price' => 121,
                'net_price' => 100,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'mail_only_server_id' => $server->id,
            'directadmin_customer_username' => 'goodtest',
        ]);

        ProviderFactory::new()->create([
            'type' => ProviderType::MAILONLY,
            'default' => true,
            'enabled' => true,
            'slug' => ProviderSlug::DIRECTADMIN,
        ]);
    }

    #[Test]
    public function listUsers(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.mail.users', [
                    'domain' => $this->domain,
                ]),
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'username' => 'mytestuser1',
                    ],
                    [
                        'username' => 'mytestuser2',
                    ],
                ],
            ]);
    }

    #[Test]
    public function listUsersForUnknownDomain(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.mail.users', [
                    'domain' => 'wrongdomain.com',
                ]),
            )
            ->assertForbidden();
    }
}
