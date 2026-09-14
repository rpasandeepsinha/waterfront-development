<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\SSL;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Enum\StatusEnum;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SslController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Infra\RtrClient\Services\RtrSslService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

#[CoversClass(SslController::class)]
class SslControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Provider $rtrProvider;

    private SslDeployment $sslDeployment;

    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->ssl()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $sslSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $this->rtrProvider = new ProviderFactory()->domainRtr()->createOne();

        $this->sslDeployment = new SslDeploymentFactory()->for($this->rtrProvider)->createOne([
            'subscription_uuid' => $sslSubscription->uuid,
        ]);

        $this->translator = self::resolve(TranslatorInterface::class);
    }

    #[Test]
    public function getDeploymentNoDnsCname(): void
    {
        $rtrMock = self::mock(RtrSslService::class);
        $rtrMock->shouldReceive('getSslCnameRecord')->andReturn(null);
        $this->app->bind(RtrSslService::class, fn () => $rtrMock);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.ssl.deployment',
                    $this->sslDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJsonFragment([
                'administrative_subscription_uuid' => $this->sslDeployment->subscription->uuid,
                'domain' => $this->sslDeployment->subscription->domain,
                'provider' => $this->sslDeployment->provider->slug,
            ])
            ->assertJsonMissingExact([
                'dnsType' => 'CNAME',
                'dnsRecord' => '_c7fbc2039e400c8ef74129ec7db1842c',
                'dnsContent' => 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
            ]);
    }

    #[Test]
    public function getDeploymentWithDnsCname(): void
    {
        $dcvDetails = new DcvDetails(
            status: StatusEnum::STATUS_ACTIVE,
            caaRecordStatus: 'ATTENTION',
            dnsRecord: sprintf('_c7fbc2039e400c8ef74129ec7db1842c.%s.', $this->sslDeployment->subscription->domain),
            dnsType: 'CNAME',
            dnsContent: 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
        );

        $rtrMock = self::mock(RtrSslService::class);
        $rtrMock->shouldReceive('getSslCnameRecord')->andReturn($dcvDetails);
        $this->app->bind(RtrSslService::class, fn () => $rtrMock);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.ssl.deployment',
                    $this->sslDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJsonFragment([
                'administrative_subscription_uuid' => $this->sslDeployment->subscription->uuid,
                'domain' => $this->sslDeployment->subscription->domain,
                'provider' => $this->sslDeployment->provider->slug,
                'dnsType' => 'CNAME',
                'dnsRecord' => '_c7fbc2039e400c8ef74129ec7db1842c',
                'dnsContent' => 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
            ]);
    }

    #[Test]
    public function getDeploymentWithDnsCnameExternalNameservers(): void
    {
        Assert::notNull($this->sslDeployment->subscription->domain);
        $domainContact = new DomainContactFactory()->for($this->customer)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->forDomain($this->sslDeployment->subscription->domain)
            ->createOne();

        new DomainDeploymentFactory()->for($this->rtrProvider)->createOne([
            'subscription_uuid' => $subscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $dnsProductGroup = ProductGroupFactory::new()->dns()->createOne();
        $dnsProduct = ProductFactory::new()->for($dnsProductGroup)->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($dnsProduct)
            ->forDomain($this->sslDeployment->subscription->domain)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withExternalNameserver()
            ->createOne();

        $dcvDetails = new DcvDetails(
            status: StatusEnum::STATUS_ACTIVE,
            caaRecordStatus: 'ATTENTION',
            dnsRecord: sprintf('_c7fbc2039e400c8ef74129ec7db1842c.%s.', $this->sslDeployment->subscription->domain),
            dnsType: 'CNAME',
            dnsContent: 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
        );

        $rtrMock = self::mock(RtrSslService::class);
        $rtrMock->shouldReceive('getSslCnameRecord')->andReturn($dcvDetails);
        $this->app->bind(RtrSslService::class, fn () => $rtrMock);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.ssl.deployment',
                    $this->sslDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJsonFragment([
                'administrative_subscription_uuid' => $this->sslDeployment->subscription->uuid,
                'domain' => $this->sslDeployment->subscription->domain,
                'provider' => $this->sslDeployment->provider->slug,
                'dnsType' => 'CNAME',
                'dnsRecord' => '_c7fbc2039e400c8ef74129ec7db1842c',
                'dnsContent' => 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
            ]);
    }

    #[Test]
    public function retryDcvSuccess(): void
    {
        $resultOk = self::mock(Result::class);
        $resultOk->shouldReceive('getStatus')->andReturn(Result::STATUS_OK);

        $sharedSslServiceMock = self::mock(CustomerSharedSslService::class);
        $sharedSslServiceMock
            ->shouldReceive('resendDcv')
            ->once()
            ->withArgs(fn ($deployment) => $deployment->is($this->sslDeployment))
            ->andReturn($resultOk);

        $this->app->bind(CustomerSharedSslService::class, fn () => $sharedSslServiceMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.ssl.retry-dcv',
                    $this->sslDeployment->subscription_uuid,
                ),
            )
            ->assertOk()
            ->assertJson([
                'message' => $this->translator->translate('ssl-subscription.success_resend_dcv'),
            ]);
    }

    #[Test]
    public function retryDcvPreconditionFailed(): void
    {
        $resultError = self::mock(Result::class);
        $resultError->shouldReceive('getStatus')->andReturn(Result::STATUS_ERROR);
        $resultError->shouldReceive('getErrorCode')->andReturn(412);

        $sharedSslServiceMock = self::mock(CustomerSharedSslService::class);
        $sharedSslServiceMock
            ->shouldReceive('resendDcv')
            ->once()
            ->withArgs(fn ($deployment) => $deployment->is($this->sslDeployment))
            ->andReturn($resultError);

        $this->app->bind(CustomerSharedSslService::class, fn () => $sharedSslServiceMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.ssl.retry-dcv',
                    $this->sslDeployment->subscription_uuid,
                ),
            )
            ->assertStatus(412)
            ->assertJson([
                'message' => $this->translator->translate('ssl-subscription.failed_resend_dcv'),
            ]);
    }
}
