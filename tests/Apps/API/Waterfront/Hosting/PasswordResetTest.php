<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversNothing]
class PasswordResetTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeployment $hostingDeployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(
                new ProductFactory()->for(
                    new ProductGroupFactory()->hosting(),
                )->createOne(),
            )
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => new ProviderFactory()->createOne([
                'type' => ProviderType::HOSTING,
                'slug' => ProviderSlug::DIRECTADMIN,
                'enabled' => true,
                'default' => true,
            ]),
            'server_id' => new ServerFactory()->directadmin()->createOne(),
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailDirectAdminDetails::getTemplateSlug(),
        ]);
    }

    #[Test]
    public function integratedServiceThrowsRuntimeException(): void
    {
        $provider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        $server = new ServerFactory()->plesk()->createOne();

        $this->hostingDeployment->update([
            'server_id' => $server->id,
            'provider_id' => $provider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute(
                    'partners.hosting.reset_password',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertServerError();
    }

    #[Test]
    public function directAdminDoesNotThrowException(): void
    {
        self::assertEmailsSend([MailDirectAdminDetails::class]);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute(
                    'partners.hosting.reset_password',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertJsonFragment(['username' => $this->hostingDeployment->directadmin_customer_username])
            ->assertOk();
    }
}
