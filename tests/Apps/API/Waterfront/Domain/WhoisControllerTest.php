<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\WhoisController;
use Waterfront\Apps\API\Waterfront\Policies\ProductPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(WhoisController::class)]
class WhoisControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for(new ProductFactory()->nlDomain())
            ->for($this->customer)
            ->createOne();

        $ownerContact = DomainContactfactory::new()
            ->for($this->customer)
            ->createOne();

        $provider = ProviderFactory::new()
            ->domainOpenProvider()
            ->createOne();

        $ownerContact->providers()
            ->attach($provider, ['external_contact' => 'owner-handle-test']);

        new DomainDeploymentFactory()
            ->for($provider)
            ->for($this->subscription)
            ->for($ownerContact, 'contactOwner')
            ->createOne();
    }

    #[Test]
    public function whoisControllerUsesDomainProviderBusinessUnit(): void
    {
        $ownerHandle = 'owner-handle-test';
        $adminHandle = 'admin-handle-test';

        $domainDeployment = $this->subscription->domainDeployment;
        self::assertNotNull($domainDeployment);

        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();
        $domainDeployment->businessUnit()->associate($businessUnit);
        $domainDeployment->save();

        $domainServiceMock = self::mock(DomainService::class);
        $policyMock = self::mock(SubscriptionPolicy::class);

        $policyMock
            ->shouldReceive('assertCanView')
            ->withArgs(fn (Subscription $subscription) => $subscription->is($this->subscription))
            ->andReturnNull();

        $nameserverResultMock = self::mock(RetrieveResult::class);
        $handleMock = self::mock(HandleInterface::class);
        $mockCustomerResponse = self::mock(RetrieveCustomerResponse::class);

        $domainServiceMock
            ->shouldReceive('checkNameservers')
            ->withArgs(fn (DomainDeployment $domainDeployment) => $domainDeployment->is($this->subscription->domainDeployment))
            ->andReturn($nameserverResultMock);

        $nameserverResultMock
            ->shouldReceive('getHandles')
            ->andReturn($handleMock);

        $handleMock
            ->shouldReceive('getOwnerHandle')
            ->andReturn($ownerHandle);

        $domainServiceMock
            ->shouldReceive('retrieveContactHandle')
            ->withArgs(
                fn (string $handle, ProviderSlug $provider, DomainProviderBusinessUnit $bu) =>
                $handle === $ownerHandle
                && $provider === $domainDeployment->provider->slug
                && $bu->is($businessUnit)
            )
            ->andReturn($mockCustomerResponse);

        $mockCustomerResponse
            ->shouldReceive('toArray')
            ->andReturn([]);

        $domainServiceMock
            ->shouldReceive('retrieveContactHandle')
            ->withArgs(
                fn (string $handle, ProviderSlug $provider, DomainProviderBusinessUnit $bu) =>
                $handle === $adminHandle
                && $provider === $domainDeployment->provider->slug
                && $bu->is($businessUnit)
            )
            ->andReturn($mockCustomerResponse);

        $handleMock
            ->shouldReceive('getAdminHandle')
            ->andReturn($adminHandle);

        $nameserverResultMock
            ->shouldReceive('getIsPrivateWhoisEnabled')
            ->andReturnFalse();

        $controller = new WhoisController(
            self::resolve(ProductPolicy::class),
            $policyMock,
            self::resolve(TranslatorInterface::class),
            $domainServiceMock
        );

        self::assertNotNull($this->subscription->domain);
        $controller->index($this->subscription->domain);
    }

    #[Test]
    public function index(): void
    {
        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.whois.index',
                    [
                        'domain' => $this->subscription->domain,
                    ]
                )
            );

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'admin' => [
                        'address' => [
                            'city' => 'Washington',
                            'country' => 'US',
                            'number' => '2',
                            'street' => 'Main Street',
                            'zipcode' => '630060',
                        ],
                        'email' => 'info@openprovider.nl',
                        'first_name' => 'John',
                        'gender' => Gender::MALE->value,
                        'last_name' => 'van Halen',
                        'organization' => 'Hosting Unlimited',
                        'phone' => '+31612345678',
                    ],
                    'is_private' => false,
                    'owner' => [
                        'address' => [
                            'city' => 'Washington',
                            'country' => 'US',
                            'number' => '2',
                            'street' => 'Main Street',
                            'zipcode' => '630060',
                        ],
                        'email' => 'info@openprovider.nl',
                        'first_name' => 'John',
                        'gender' => Gender::MALE->value,
                        'last_name' => 'van Halen',
                        'organization' => 'Hosting Unlimited',
                        'phone' => '+31612345678',
                    ],
                ],
            ]);
    }
}
