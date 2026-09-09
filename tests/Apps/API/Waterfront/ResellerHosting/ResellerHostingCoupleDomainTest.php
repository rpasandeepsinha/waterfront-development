<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\ResellerHosting;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingSslCoupleException;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class ResellerHostingCoupleDomainTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscriptionResellerDomain1;

    private Subscription $subscriptionResellerDomain2;

    private Subscription $freeDnsSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        $domainDirectAdmin = 'openprovider.nl';
        $domainPlesk = 'openplesk.nl';

        $this->customer = new CustomerFactory()->createOne();

        $daProviderKey = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true])->id;
        $pleskProviderKey = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true])->id;
        $openProviderKey = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'default' => true,
            'enabled' => true,
        ])->id;
        $openProviderSslKey = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'default' => true,
            'enabled' => true,
        ])->id;

        $resellerProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::RESELLER_HOSTING,
            'name' => 'Reseller Hosting',
        ]);

        $sslProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::SSL,
            'name' => 'SSL',
        ]);

        $resellerProduct = new ProductFactory()->for($resellerProductGroup)->createOne();
        $sslProduct = new ProductFactory()->for($sslProductGroup)->createOne();

        $this->subscriptionResellerDomain1 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'domain' => $domainDirectAdmin,
        ]);

        $this->subscriptionResellerDomain2 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'domain' => $domainPlesk,
        ]);

        $subscriptionSslDomain1 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $sslProduct->uuid,
            'domain' => $domainDirectAdmin,
        ]);

        $subscriptionSslDomain2 = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $sslProduct->uuid,
            'domain' => $domainPlesk,
        ]);

        $serverDA = new ServerFactory()->directadmin()->createOne();
        $serverPlesk = new ServerFactory()->createOne();

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscriptionResellerDomain1->uuid,
            'server_id' => $serverDA->id,
            'directadmin_customer_username' => 'DAServerUSer',
            'provider_id' => $daProviderKey,
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscriptionResellerDomain2->uuid,
            'server_id' => $serverPlesk->id,
            'plesk_customer_username' => 'PleskServerUser',
            'provider_id' => $pleskProviderKey,
        ]);

        new SslDeploymentFactory()->create([
            'subscription_uuid' => $subscriptionSslDomain1->uuid,
            'provider_id' => $openProviderSslKey,
            'certificate_id' => random_int(1, 10000),
            'request_id' => random_int(1, 10000),
        ]);

        new SslDeploymentFactory()->create([
            'subscription_uuid' => $subscriptionSslDomain2->uuid,
            'provider_id' => $openProviderSslKey,
            'certificate_id' => random_int(1, 10000),
            'request_id' => random_int(1, 10000),
        ]);

        // Create procuct group Domein
        $domainProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Domein',
            'slug' => 'extension',
        ]);

        //Create a domein Product
        $domainProduct = new ProductFactory()->createOne([
            'product_group_id' => $domainProductGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
            'description' => '.nl Domein',
            'orderable' => 1,
            'weight' => 1,
        ]);

        $domainSubscriptionDomainDA = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => $domainDirectAdmin,
            'product_uuid' => $domainProduct->uuid,
            'gross_price' => 600,
            'net_price' => 600,
            'contract_period' => 12,
        ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $domainSubscriptionDomainDA->uuid,
            'last_result' => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id' => $openProviderKey,
        ]);

        $domainSubscriptionDomainPlesk = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => $domainPlesk,
            'product_uuid' => $domainProduct->uuid,
            'gross_price' => 600,
            'net_price' => 600,
            'contract_period' => 12,
        ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $domainSubscriptionDomainPlesk->uuid,
            'last_result' => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id' => $openProviderKey,
        ]);

        $productGroupDns = new ProductGroupFactory()->dns()->createOne();
        $freeDnsProduct = new ProductFactory()->for($productGroupDns)->has(
            new ProductSpecFactory()
                ->state([
                    'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT,
                    'value' => true,
                ])
        )->createOne([
            'name' => ProductType::FREE_DNS->value,
            'slug' => ProductType::FREE_DNS->value,
        ]);

        $this->freeDnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->forDomain($domainPlesk)
            ->parentSubscription($domainSubscriptionDomainPlesk)
            ->createOne();

        DnsDeploymentFactory::new()->for($this->freeDnsSubscription)->create();

        $freeDnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->forDomain($domainDirectAdmin)
            ->parentSubscription($domainSubscriptionDomainDA)
            ->createOne();

        DnsDeploymentFactory::new()->for($freeDnsSubscription)->createOne();
    }

    #[Test]
    public function coupleDomain(): void
    {
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domain,
            'uuid' => $this->subscriptionResellerDomain1->uuid,
        ];

        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        $certificateManager = self::resolve(CertificateManager::class);
        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');
        $csrManagerMock->method('getRawCSR')->willReturn('A Key');

        $certificateManager->saveRootCertificate($domain, $rootCertificate);
        $certificateManager->saveMainCertificate($domain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($domain, $intermediateCertificate);
        $this->app->bind(CsrManager::class, fn (): CsrManager => $csrManagerMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertOk()
            ->assertSee(self::resolve(TranslatorInterface::class)->translate('resellerhosting.couple-domain-success'));
    }

    #[Test]
    public function coupleDomainAllowCoupleHostingSpecFalse(): void
    {
        $domain = 'openprovider.nl';

        $hostingCouplingSpec = $this->freeDnsSubscription->product->productSpecs()->where('name', ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)->firstOrFail();
        $hostingCouplingSpec->value = false;
        $hostingCouplingSpec->save();

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domain,
            'uuid' => $this->subscriptionResellerDomain1->uuid,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertForbidden();
    }

    #[Test]
    public function coupleDomainWrongServer(): void
    {
        $domain = 'openplesk.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain2->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domain,
            'uuid' => $this->subscriptionResellerDomain2->uuid,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertUnprocessable()
            ->assertSee(self::resolve(TranslatorInterface::class)->translate('resellerhosting.couple-domain-binding'));
    }

    #[Test]
    public function coupleDomainSslFailed(): void
    {
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domain,
            'uuid' => $this->subscriptionResellerDomain1->uuid,
        ];

        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');
        $csrManagerMock->method('getRawCSR')->willReturn('A Key');

        $this->app->bind(CsrManager::class, fn (): CsrManager => $csrManagerMock);

        $resellerHostingService = self::createStub(ResellerHostingService::class);
        $resellerHostingService->method('coupleExistingDomain')->willThrowException(new ResellerHostingSslCoupleException());

        $this->app->bind(ResellerHostingService::class, fn (): ResellerHostingService => $resellerHostingService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertUnprocessable()
            ->assertSee(self::resolve(TranslatorInterface::class)->translate('resellerhosting.install-certificate-failed'));
    }

    #[Test]
    public function coupleDomainInValidUuid(): void
    {
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => 'ccd94f78-324e-11ec-b4ab-f80f41c85a48',
            'reseller_sub_username' => 'username',
            'domain' => $domain,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
            )
            ->assertNotFound();
    }

    #[Test]
    public function coupleDomainUuidMismatch(): void
    {
        $customer = new CustomerFactory()->createOne();
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domain,
        ];

        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
            )
            ->assertForbidden()
            ->assertJsonFragment([
                    'message' => 'This action is unauthorized.',
                ]);
    }

    #[Test]
    public function coupleDomainInValidDomain(): void
    {
        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => '<!evildomain.gr#',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function coupleDomainUnknownDomain(): void
    {
        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => 'unknowndomain.nl',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
            )
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function coupleDomainInvalidUsername(): void
    {
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => '<evil>username@#',
            'domain' => $domain,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
            )
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function coupleDomainUsernameToLong(): void
    {
        $domain = 'openprovider.nl';

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => substr(str_shuffle('0123456789abcdefghijklmnopqrstvwxyz'), 0, 21),
            'domain' => $domain,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
            )
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function coupleDomainReturnsUnprocessableWhenNoDnsSubscriptionForCoupledDomain(): void
    {
        $domainWithoutDns = 'no-dns.nl';

        $domainProduct = Subscription::query()
            ->where('domain', 'openprovider.nl')
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->firstOrFail()
            ->product;

        new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => $domainWithoutDns,
            'product_uuid' => $domainProduct->uuid,
            'gross_price' => 600,
            'net_price' => 600,
            'contract_period' => 12,
        ]);

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $domainWithoutDns,
            'uuid' => $this->subscriptionResellerDomain1->uuid,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertUnprocessable()
            ->assertSee(self::resolve(TranslatorInterface::class)->translate('resellerhosting.couple-domain-no-dns-subscription'));
    }

    #[Test]
    public function coupleDomainSucceedsWhenResellerSubscriptionDomainDiffersFromCoupledDomain(): void
    {
        $coupledDomain = 'openprovider.nl';
        $resellerInternalHostname = 'studisb53.fiftythree.axc.nl';

        // Simulate the production scenario: the reseller hosting subscription itself
        // has an internal AXC hostname as its domain (or any other domain than the
        // one being coupled). The DNS-subscription check must look at the coupled
        // domain from the request, not at the reseller subscription's own domain.
        $this->subscriptionResellerDomain1->domain = $resellerInternalHostname;
        $this->subscriptionResellerDomain1->save();

        $rootCertificate = require __DIR__ . '/data/root_certificate.php';
        $mainCertificate = require __DIR__ . '/data/main_certificate.php';
        $intermediateCertificate = require __DIR__ . '/data/intermediate_certificate.php';

        $certificateManager = self::resolve(CertificateManager::class);
        $csrManagerMock = self::createStub(CsrManager::class);
        $csrManagerMock->method('getPrivateKey')->willReturn('A Key');
        $csrManagerMock->method('getRawCSR')->willReturn('A Key');

        $certificateManager->saveRootCertificate($coupledDomain, $rootCertificate);
        $certificateManager->saveMainCertificate($coupledDomain, $mainCertificate);
        $certificateManager->saveIntermediateCertificate($coupledDomain, $intermediateCertificate);
        $this->app->bind(CsrManager::class, fn (): CsrManager => $csrManagerMock);

        $parameters = [
            'resellerHostingDeployment' => $this->subscriptionResellerDomain1->uuid,
            'reseller_sub_username' => 'username',
            'domain' => $coupledDomain,
            'uuid' => $this->subscriptionResellerDomain1->uuid,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.reseller-hosting.couple-domain', $parameters),
                $parameters
            )
            ->assertOk()
            ->assertSee(self::resolve(TranslatorInterface::class)->translate('resellerhosting.couple-domain-success'));
    }
}
