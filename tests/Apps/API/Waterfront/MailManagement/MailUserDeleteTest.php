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
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class MailUserDeleteTest extends IntegrationTestCase
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
                'contract_period' => '12',
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
            'slug' => ProviderSlug::DIRECTADMIN,
            'default' => true,
            'enabled' => true,
        ]);
    }

    #[Test]
    public function deleteUser(): void
    {
        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.mail.delete-user', [
                    'domain' => $this->domain,
                    'username' => 'mytestuser1',
                ]),
            )
            ->assertOk()
            ->assertExactJson([
                'status' => true,
            ]);
    }

    #[Test]
    public function deleteUserForUnknownDomain(): void
    {
        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.mail.delete-user', [
                    'domain' => 'wrongdomain.nl',
                    'username' => 'mytestuser1',
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function deleteUserForUnknownUsername(): void
    {
        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.mail.delete-user', [
                    'domain' => 'example.com',
                    'username' => 'nonexistinguser',
                ]),
            )
            ->assertNotFound()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('mail-providers.errors.username-does-not-exist'),
            ]);
    }
}
