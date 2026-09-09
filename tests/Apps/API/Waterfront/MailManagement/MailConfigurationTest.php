<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\MailManagement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\MailController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(MailController::class)]
class MailConfigurationTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $product = new ProductFactory()->mailOnly()->createOne();

        new ProductSpecFactory()
        ->for($product)
        ->createOne([
            'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $this->subscription = new SubscriptionFactory()->for($this->customer)->for($product)->createOne();

        $mailProvider = ProviderFactory::new()->createOne(['type' => ProviderType::MAILONLY, 'slug' => ProviderSlug::DIRECTADMIN, 'default' => true, 'enabled' => true]);

        new HostingDeploymentFactory()
            ->for(new ServerFactory()->createOne(), 'mailOnlyServer')
            ->for($mailProvider, 'mailProvider')
            ->for($this->subscription)
            ->createOne();
    }

    #[Test]
    public function mailConfiguration(): void
    {
        $this->actingAsCustomer($this->customer)
             ->getJson(
                 $this->generateRoute('partners.mail.configuration', [
                     'domain' => $this->subscription->domain,
                 ])
             )
             ->assertOk()
             ->assertJson([
                'data' => [
                    'imap_host' => 'mail.sandwaveio.dev',
                    'imap_port' => 993,
                    'imap_encryption' => 'SSL',
                    'pop3_host' => 'mail.sandwaveio.dev',
                    'pop3_port' => 995,
                    'pop3_encryption' => 'SSL',
                    'smtp_host' => 'smtp.sandwaveio.dev',
                    'smtp_port' => 465,
                    'smtp_encryption' => 'SSL',
                    'dns' => [
                        [
                            'type' => 'MX',
                            'name' => '',
                            'value' => 'mail.sandwaveio.dev',
                            'prio' => 10,
                        ],
                        [
                            'type' => 'MX',
                            'name' => '',
                            'value' => 'fallback.sandwaveio.dev',
                            'prio' => 20,
                        ],
                    ],
                 ],
             ]);
    }

    #[Test]
    public function configurationForUnknownDomain(): void
    {
        $this->actingAsCustomer($this->customer)
             ->getJson(
                 $this->generateRoute('partners.mail.configuration', [
                     'domain' => 'wrongdomain.com',
                 ])
             )->assertForbidden();
    }
}
