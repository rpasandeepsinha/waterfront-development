<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository as SslDeploymentRepository;
use Waterfront\Domain\Ssl\Services\SslDnsManagementResolver;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SslDnsManagementResolver::class)]
class SslDnsManagementResolverTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private string $externalDomainUuid;

    private string $freeDnsUuid;

    private string $sslSingleDomainUuid;

    private int $sslProviderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->externalDomainUuid = ProductFactory::new()->nlDomain()->createOne()->uuid;
        $this->freeDnsUuid = ProductFactory::new()->freeDns()->createOne()->uuid;
        $this->sslSingleDomainUuid = ProductFactory::new()->sslSingleDomain()->createOne()->uuid;

        $this->sslProviderId = ProviderFactory::new()
            ->sslRtr()
            ->createOne()
            ->id;
    }

    #[Test]
    public function hasManagedDnsIsFalseWhenNoDnsChild(): void
    {
        ['sslDeployment' => $sslDeployment] = $this->makeDomainWithDnsAndSsl('no-dns.example', null);
        $resolver = self::resolve(SslDnsManagementResolver::class);

        self::assertFalse($resolver->hasManagedDns($sslDeployment));
    }

    #[Test]
    public function hasManagedDnsIsFalseWhenDnsChildExternal(): void
    {
        ['sslDeployment' => $sslDeployment] = $this->makeDomainWithDnsAndSsl('external.example', NameserverType::EXTERNAL);
        $resolver = self::resolve(SslDnsManagementResolver::class);

        self::assertFalse($resolver->hasManagedDns($sslDeployment));
    }

    #[Test]
    public function hasManagedDnsIsTrueWhenDnsChildInternal(): void
    {
        ['sslDeployment' => $sslDeployment] = $this->makeDomainWithDnsAndSsl('internal.example', NameserverType::INTERNAL);
        $resolver = self::resolve(SslDnsManagementResolver::class);

        self::assertTrue($resolver->hasManagedDns($sslDeployment));
    }

    #[Test]
    public function hasManagedDnsIsTrueWhenDnsChildVanity(): void
    {
        ['sslDeployment' => $sslDeployment] = $this->makeDomainWithDnsAndSsl('vanity.example', NameserverType::VANITY);
        $resolver = self::resolve(SslDnsManagementResolver::class);

        self::assertTrue($resolver->hasManagedDns($sslDeployment));
    }

    #[Test]
    public function repositoryGetReminderCandidatesRespectsDateWindow(): void
    {
        ['sslDeployment' => $in3] = $this->makeDomainWithDnsAndSsl('in-3.example', null, daysUntilExpiry: 3);
        ['sslDeployment' => $in7] = $this->makeDomainWithDnsAndSsl('in-7.example', NameserverType::EXTERNAL, daysUntilExpiry: 7);
        ['sslDeployment' => $out8] = $this->makeDomainWithDnsAndSsl('out-8.example', NameserverType::INTERNAL, daysUntilExpiry: 8);
        ['sslDeployment' => $past] = $this->makeDomainWithDnsAndSsl('past.example', null, daysUntilExpiry: -1);

        /** @var SslDeploymentRepository $sslDeploymentRepository */
        $sslDeploymentRepository = self::resolve(SslDeploymentRepository::class);

        $candidates = $sslDeploymentRepository->getReminderCandidates(7);
        $ids = $candidates->pluck('id')->all();

        self::assertContains($in3->id, $ids, 'end_date at +3d must be included');
        self::assertContains($in7->id, $ids, 'end_date at +7d (boundary) must be included');

        self::assertNotContains($out8->id, $ids, 'end_date at +8d must be excluded');
        self::assertNotContains($past->id, $ids, 'past end_date must be excluded');
    }

    #[Test]
    public function repositoryGetReminderCandidatesSkipsAdministrativelyEnded(): void
    {
        ['sslDeployment' => $sslDeployment] = $this->makeDomainWithDnsAndSsl('ended.example', null, daysUntilExpiry: 2);

        $sslDeployment->subscription->update([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        /** @var SslDeploymentRepository $sslDeploymentRepository */
        $sslDeploymentRepository = self::resolve(SslDeploymentRepository::class);

        $candidates = $sslDeploymentRepository->getReminderCandidates(7);

        self::assertNotContains($sslDeployment->id, $candidates->pluck('id')->all());
    }

    /**
     * @return array{
     *   domainParent:Subscription,
     *   dnsChild:(Subscription|null),
     *   sslSubscription:Subscription,
     *   sslDeployment:SslDeployment
     * }
     */
    private function makeDomainWithDnsAndSsl(string $domain, ?NameserverType $nsType, int $daysUntilExpiry = 3): array
    {
        $customer = CustomerFactory::new()->createOne();
        $endDate = CarbonImmutable::today()->addDays($daysUntilExpiry);

        $domainParent = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain($domain)
            ->state([
                'customer_id' => $customer->id,
                'product_uuid' => $this->externalDomainUuid,
            ])
            ->createOne();

        $dnsChild = null;
        if ($nsType !== null) {
            $dnsChild = SubscriptionFactory::new()
                ->administrativeStatusActive()
                ->forDomain($domain)
                ->parentSubscription($domainParent)
                ->state([
                    'customer_id' => $customer->id,
                    'product_uuid' => $this->freeDnsUuid,
                ])
                ->createOne();

            $dnsFactory = match ($nsType) {
                NameserverType::EXTERNAL => DnsDeploymentFactory::new()->withExternalNameserver(),
                NameserverType::INTERNAL => DnsDeploymentFactory::new()->withInternalNameserver(),
                NameserverType::VANITY => DnsDeploymentFactory::new()->withVanityNameserver(),
            };

            $dnsFactory->state(['subscription_uuid' => $dnsChild->uuid])->createOne();
        }

        $sslSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain($domain)
            ->parentSubscription($domainParent)
            ->state([
                'customer_id' => $customer->id,
                'product_uuid' => $this->sslSingleDomainUuid,
                'end_date' => $endDate,
            ])
            ->createOne();

        /** @var SslDeployment $sslDeployment */
        $sslDeployment = SslDeploymentFactory::new()
            ->state([
                'provider_id' => $this->sslProviderId,
                'subscription_uuid' => $sslSubscription->uuid,
            ])
            ->createOne();

        return [
            'domainParent' => $domainParent,
            'dnsChild' => $dnsChild,
            'sslSubscription' => $sslSubscription,
            'sslDeployment' => $sslDeployment,
        ];
    }
}
