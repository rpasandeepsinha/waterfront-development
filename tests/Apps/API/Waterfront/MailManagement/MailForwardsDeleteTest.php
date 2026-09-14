<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\MailManagement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(MailManagementService::class)]
class MailForwardsDeleteTest extends IntegrationTestCase
{
    private Customer $customer;

    private string $domain;

    private HostingDeployment $hostingDeploymentForMail;

    private Server $server;

    private MailManagementService&MockObject $mailOnlyService;

    public function setUp(): void
    {
        parent::setUp();

        $this->domain = 'example.com';
        $this->customer = CustomerFactory::new()->createOne();

        $this->server = ServerFactory::new()->createOne([
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);

        $product = ProductFactory::new()->emailStart()->createOne();

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $subscription = SubscriptionFactory::new()
            ->for($product)
            ->for($this->customer)
            ->forDomain($this->domain)
            ->createOne();

        $mailProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::MAILONLY,
            'slug' => ProviderSlug::DIRECTADMIN,
        ]);

        /** @var HostingDeployment $hostingDeploymentForMail */
        $hostingDeploymentForMail = HostingDeploymentFactory::new()
            ->for($subscription)
            ->createOne([
                'mail_only_server_id' => $this->server->id,
                'mail_only_provider_id' => $mailProvider->id,
                'directadmin_customer_username' => 'good-test',
            ])
            ->fresh([
                'subscription.product.productGroup',
                'subscription.product.productSpecs',
                'subscription.customer',
                'subscription.transfers',
            ]); // An extra "fresh" so it's not "newly" created in the test

        $this->hostingDeploymentForMail = $hostingDeploymentForMail;

        $this->mailOnlyService = self::createMock(MailManagementService::class);
        $this->app->bind(MailManagementService::class, fn (): MailManagementService => $this->mailOnlyService);
    }

    #[Test]
    public function deleteForwards(): void
    {
        $source = 'example_source';

        $this->mailOnlyService
            ->expects(self::once())
            ->method('deleteEmailForward')
            ->with($this->hostingDeploymentForMail, $source)
            ->willReturn(true);

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.mail.delete-forward', [
                    'hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid,
                    'source' => $source,
                ]),
            )
            ->assertNoContent();
    }

    #[Test]
    public function deleteForwardsThrowsException(): void
    {
        $source = 'another_source';

        $this->mailOnlyService
            ->expects(self::once())
            ->method('deleteEmailForward')
            ->willThrowException(
                new EmailForwardException(
                    $this->server,
                    $this->domain,
                    'good-test',
                    [
                        'source' => $source,
                    ],
                ),
            );

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.mail.delete-forward', [
                    'hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid,
                    'source' => $source,
                ]),
            )
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'mail-providers.errors.delete-forward-error',
                'errors' => [],
            ]);
    }
}
