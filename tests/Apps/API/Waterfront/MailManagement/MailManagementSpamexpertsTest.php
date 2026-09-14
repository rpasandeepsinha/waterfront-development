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
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class MailManagementSpamexpertsTest extends IntegrationTestCase
{
    private Customer $customer;

    private string $domain;

    private HostingDeployment $hostingDeployment;

    private Product $mailOnlyproduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->domain = 'spamexpertstest.com';
        $this->customer = new CustomerFactory()->createOne();

        $server = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN_MAIL,
            'hostname' => $this->domain,
        ]);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'hosting']);
        $this->mailOnlyproduct = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'Mail Only',
            'slug' => 'hosting_mail_only',
        ]);

        new ProductSpecFactory()->for($this->mailOnlyproduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailOnlyproduct)
            ->createOne([
                'domain' => $this->domain,
                'contract_period' => 12,
                'gross_price' => 121,
                'net_price' => 100,
            ]);

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
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
    public function spamexpertsSso(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute(
                'partners.mail.spamexperts-sso',
                $this->hostingDeployment->subscription_uuid,
            ))
            ->assertJson([
                'url' => "https://spamexperts.sandwaveio.dev/?authticket=my-token-for-domain-$this->domain",
            ]);
    }

    #[Test]
    public function spamexpertsSsoForUnknownUuid(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.mail.spamexperts-sso', '223234-222-daga-2-2-2'))
            ->assertNotFound();
    }

    #[Test]
    public function spamexpertsSsoException(): void
    {
        // Create a hosting provider (different type) to force an exception scenario.
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => 'directadmin',
            'hostname' => $this->domain,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailOnlyproduct)
            ->createOne([
                'domain' => 'exception.com',
                'contract_period' => 12,
                'gross_price' => 121,
                'net_price' => 100,
            ]);

        $hostingSub = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'mail_only_server_id' => $server->id,
            'directadmin_customer_username' => 'badtest',
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.mail.spamexperts-sso', $hostingSub->subscription_uuid))
            ->assertServerError()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('mail-providers.errors.spamexperts-sso-error'),
            ]);
    }
}
