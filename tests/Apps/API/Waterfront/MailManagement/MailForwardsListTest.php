<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\MailManagement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminEmailForward;

#[CoversClass(MailManagementService::class)]
class MailForwardsListTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeployment $hostingDeploymentForMail;

    private MailManagementService&Stub $mailOnlyService;

    public function setUp(): void
    {
        parent::setUp();

        $domain = 'example.com';
        $this->customer = CustomerFactory::new()->createOne();

        $server = ServerFactory::new()
            ->createOne([
                'type' => ServerType::DIRECTADMIN_MAIL,
            ]);

        $product = ProductFactory::new()
            ->emailStart()
            ->createOne();

        new ProductSpecFactory()
            ->for($product)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $subscription = SubscriptionFactory::new()
            ->for($product)
            ->for($this->customer)
            ->forDomain($domain)
            ->createOne();

        $mailProvider = ProviderFactory::new()
            ->createOne([
                'type' => ProviderType::MAILONLY,
                'slug' => ProviderSlug::DIRECTADMIN,
            ]);

        /** @var HostingDeployment $hostingDeploymentForMail */
        $hostingDeploymentForMail = HostingDeploymentFactory::new()
            ->for($subscription)
            ->createOne([
                'mail_only_server_id' => $server->id,
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

        $this->mailOnlyService = self::createStub(MailManagementService::class);
        $this->app->bind(MailManagementService::class, fn (): MailManagementService => $this->mailOnlyService);
    }

    #[Test]
    public function listForwards(): void
    {
        $this->mailOnlyService->method('getEmailForwardsFromDeployment')
            ->willReturn([
                new DirectAdminEmailForward(
                    source: 'source1',
                    destinations: [
                        'destination1@mail.test',
                        'destination2@mail.test',
                    ],
                ),
                new DirectAdminEmailForward(
                    source: 'source2',
                    destinations: [
                        'destination3@mail.test',
                    ],
                ),
            ]);

        $responseArray = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.mail.forwards', ['hostingDeployment' => $this->hostingDeploymentForMail->subscription_uuid])
            );

        $responseArray->assertExactJson(
            [
                [
                    'source' => 'source1',
                    'destinations' => [
                        'destination1@mail.test',
                        'destination2@mail.test',
                    ],
                ],
                [
                    'source' => 'source2',
                    'destinations' => [
                        'destination3@mail.test',
                    ],
                ],
            ]
        );
    }
}
