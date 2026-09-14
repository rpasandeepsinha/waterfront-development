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
class MailForwardsCreateTest extends IntegrationTestCase
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
    public function createForwards(): void
    {
        $source = 'source';
        $destinations = [
            'destination1@mail.test',
            'destination2@mail.test',
        ];

        $this->mailOnlyService
            ->expects(self::once())
            ->method('createEmailForward')
            ->with($this->hostingDeploymentForMail, $source, $destinations)
            ->willReturn(true);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-forward', [
                    'hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid,
                ]),
                [
                    'source' => $source,
                    'destinations' => $destinations,
                ],
            )
            ->assertCreated();
    }

    #[Test]
    public function createForwardsUnprocessable(): void
    {
        $source = 'source@email.test';
        $destinations = [
            'also-not-an-email',
        ];

        $this->mailOnlyService
            ->expects(self::never())
            ->method('createEmailForward')
            ->with($this->hostingDeploymentForMail, $source, $destinations)
            ->willReturn(true);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-forward', [
                    'hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid,
                ]),
                [
                    'source' => $source,
                    'destinations' => $destinations,
                ],
            )
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Dit veld formaat is ongeldig. (and 1 more error)',
                'errors' => [
                    'source' => [
                        'Dit veld formaat is ongeldig.',
                    ],
                    'destinations.0' => [
                        'Dit veld dient een geldig emailadres te zijn.',
                    ],
                ],
            ]);
    }

    #[Test]
    public function createForwardsThrowsException(): void
    {
        $source = 'my_source';
        $destinations = [
            'exception@mail.test',
        ];

        $this->mailOnlyService
            ->expects(self::once())
            ->method('createEmailForward')
            ->with($this->hostingDeploymentForMail, $source, $destinations)
            ->willThrowException(
                new EmailForwardException(
                    $this->server,
                    $this->domain,
                    'good-test',
                    [
                        'source' => $source,
                        'destination' => $destinations,
                    ],
                ),
            );

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-forward', [
                    'hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid,
                ]),
                [
                    'source' => $source,
                    'destinations' => $destinations,
                ],
            )
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'mail-providers.errors.create-forward-error',
                'errors' => [],
            ]);
    }
}
