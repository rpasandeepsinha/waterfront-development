<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DomainContacts;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class DomainContactLinkTest extends IntegrationTestCase
{
    public const string DOMAIN = 'domain.com';

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $rtrService = self::resolve(RtrService::class);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                200,
                [],
                (string) json_encode($this->app->path() . '/../Modules/RtrClient/Tests/data/contact_valid.php'),
            ),
            new Response(
                200,
                [],
                (string) json_encode($this->app->path() . '/../Modules/RtrClient/Tests/data/domain_details_valid.php'),
            ),
        ]);
        $rtrService->setClient($sdk);

        $this->customer = new CustomerFactory()->createOne();
    }

    #[DataProvider('linkProvider')]
    #[Test]
    public function link(
        ProviderSlug $driverSlug,
        ?string $externalHandle = null,
    ): void {
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => $driverSlug,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'domain.com',
        ]);

        $subscription1 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'domain1.com',
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription1->uuid,
        ]);

        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        if ($externalHandle === 'EXTERNAL_HANDLE') {
            $contact->providers()->attach($provider, ['external_contact' => $externalHandle]);
        }

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.link', [
                    'contact' => $contact->id,
                    'domains' => [
                        [
                            'domain' => 'domain.com',
                            'type' => 'owner',
                        ],
                        [
                            'domain' => 'domain1.com',
                            'type' => 'owner',
                        ],
                    ],
                ]),
            )
            ->assertOk()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('domain-contact.domain-contacts-link-success'),
            ]);

        self::assertInstanceOf(DomainDeployment::class, $subscription->domainDeployment);

        self::assertDatabaseHas(
            'domain_deployments',
            [
                'id' => $subscription->domainDeployment->id,
                'contact_owner_id' => $contact->id,
            ],
        );

        self::assertInstanceOf(DomainDeployment::class, $subscription1->domainDeployment);

        self::assertDatabaseHas(
            'domain_deployments',
            [
                'id' => $subscription1->domainDeployment->id,
                'contact_owner_id' => $contact->id,
            ],
        );
    }

    #[DataProvider('linkProvider')]
    #[Test]
    public function linkWrongDomain(
        ProviderSlug $driverSlug,
        ?string $externalHandle = null,
    ): void {
        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.link', [
                    'contact' => $contact->id,
                    'domains' => [
                        [
                            'domain' => 'error.com',
                            'type' => 'owner',
                        ],
                    ],
                ]),
            )
            ->assertForbidden();
    }

    #[DataProvider('linkProvider')]
    #[Test]
    public function linkCanceledDomain(
        ProviderSlug $driverSlug,
        ?string $externalHandle = null,
    ): void {
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => $driverSlug,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'domain.com',
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        if ($externalHandle === 'EXTERNAL_HANDLE') {
            $contact->providers()->attach($provider, ['external_contact' => $externalHandle]);
        }

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.link', [
                    'contact' => $contact->id,
                    'domains' => [
                        [
                            'domain' => 'domain.com',
                            'type' => 'owner',
                        ],
                    ],
                ]),
            )
            ->assertForbidden();
    }

    /**
     * @return array<int, array<int, ProviderSlug|string|null>>
     */
    public static function linkProvider(): array
    {
        return [
            [ProviderSlug::OPEN_PROVIDER, null],
            [ProviderSlug::OPEN_PROVIDER, 'EXTERNAL_HANDLE'],
        ];
    }
}
