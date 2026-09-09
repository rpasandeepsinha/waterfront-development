<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(DomainDeploymentRepository::class)]
class DomainSubscriptionRepositoryTest extends IntegrationTestCase
{
    private Product $domainProduct;

    private Product $freeDnsProduct;

    private DomainDeploymentRepository $domainRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domainProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->extension())
            ->createOne();

        $this->freeDnsProduct = new ProductFactory()->freeDns()
            ->createOne();

        $this->domainRepository = new DomainDeploymentRepository();
    }

    #[Test]
    public function getActiveByDomain(): void
    {
        $activeDomain = 'active-domain.com';

        $activeDomainDeployment = new DomainDeploymentFactory()
            ->for(
                new ProviderFactory()
                    ->domainOpenProvider()
                    ->createOne()
            )
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($this->domainProduct)
                    ->forDomain($activeDomain)
                    ->administrativeStatusActive(),
                'subscription'
            )
            ->createOne();

        $deployment = $this->domainRepository->getActiveDeploymentByDomain($activeDomain);

        self::assertInstanceOf(DomainDeployment::class, $deployment);
        self::assertSame($activeDomain, $activeDomainDeployment->subscription->domain);
    }

    #[Test]
    public function getDnsChildSubscription(): void
    {
        $domain = 'domain-with-child-dns-sub.nl';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->domainProduct)
            ->forDomain($domain)
            ->createOne();

        $dnsChildSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->createOne(['parent_subscription_id' => $domainSubscription->id]);

        $otherChildSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->createOne(['parent_subscription_id' => $domainSubscription->id]);

        self::assertCount(2, $domainSubscription->refresh()->children);

        $retrievedChild = $this->domainRepository->getDnsChildSubscription($domainSubscription);

        self::assertNotNull($retrievedChild);
        self::assertNotSame($otherChildSubscription->uuid, $retrievedChild->uuid);
        self::assertSame($dnsChildSubscription->uuid, $retrievedChild->uuid);
    }

    #[Test]
    public function nullForGetDnsChildSubscription(): void
    {
        $domain = 'domain-without-child-dns-sub.nl';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->domainProduct)
            ->forDomain($domain)
            ->createOne();

        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->createOne(['parent_subscription_id' => $domainSubscription->id]);

        self::assertCount(1, $domainSubscription->refresh()->children);

        $retrievedChild = $this->domainRepository->getDnsChildSubscription($domainSubscription);

        self::assertNull($retrievedChild);
    }

    #[Test]
    public function getActiveByDomainReturnNull(): void
    {
        $nonExistingDomain = 'not-in-db.com';
        $inactiveDomain = 'inactive-domain.com';

        new DomainDeploymentFactory()
            ->for(
                new ProviderFactory()
                    ->domainOpenProvider()
                    ->createOne()
            )
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($this->domainProduct)
                    ->forDomain($inactiveDomain)
                    ->administrativeStatusInactive(),
                'subscription'
            )
            ->createOne();

        self::assertNull($this->domainRepository->getActiveDeploymentByDomain($inactiveDomain));
        self::assertNull($this->domainRepository->getActiveDeploymentByDomain($nonExistingDomain));
    }

    #[Test]
    public function getDomainProviderCredentials(): void
    {
        $domain = 'domain-with-rtr-credentials.com';
        $businessUnitWaterfront = DomainProviderBusinessUnitFactory::new()->waterfront()->createOne();
        $businessUnitArgeweb = DomainProviderBusinessUnitFactory::new()->argeweb();

        RtrProviderCredentialsFactory::new()
            ->for($businessUnitArgeweb)
            ->createOne();

        $rtrCredentials = RtrProviderCredentialsFactory::new()
            ->for($businessUnitWaterfront)
            ->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->withRtrProvider()
            ->withSubscription($this->domainProduct, ['domain' => $domain])
            ->for($businessUnitWaterfront, 'businessUnit')
            ->createOne();

        $credentials = $this->domainRepository->getDomainProviderCredentials($domainDeployment->provider->slug, $businessUnitWaterfront);
        self::assertInstanceOf(RtrProviderCredentials::class, $credentials);
        self::assertSame($rtrCredentials->id, $credentials->id);
    }

    #[Test]
    public function getDomainProviderCredentialsThrowsException(): void
    {
        $domain = 'domain-with-incorrect-provider.com';
        $businessUnitWaterfront = DomainProviderBusinessUnitFactory::new()->waterfront()->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->withPlaceholderProvider()
            ->withSubscription($this->domainProduct, ['domain' => $domain])
            ->for($businessUnitWaterfront, 'businessUnit')
            ->createOne();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIs(
            'Invalid provider type [placeholder] for domain provider credentials'
        );
        $this->domainRepository->getDomainProviderCredentials($domainDeployment->provider->slug, $businessUnitWaterfront);
    }

    #[Test]
    public function getDomainProviderCredentialsMissing(): void
    {
        $domain = 'domain-with-missing-credentials.com';
        $businessUnitWaterfront = DomainProviderBusinessUnitFactory::new()->waterfront()->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->withRtrProvider()
            ->withSubscription($this->domainProduct, ['domain' => $domain])
            ->for($businessUnitWaterfront, 'businessUnit')
            ->createOne();

        self::expectException(ModelNotFoundException::class);
        self::expectExceptionMessageIs(
            'No query results for model [Waterfront\Domain\Domains\Models\RtrProviderCredentials].'
        );
        $this->domainRepository->getDomainProviderCredentials($domainDeployment->provider->slug, $businessUnitWaterfront);
    }
}
