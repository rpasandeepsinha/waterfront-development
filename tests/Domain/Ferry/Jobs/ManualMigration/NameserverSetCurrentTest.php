<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs\ManualMigration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\Ferry\Jobs\ManualMigration\NameserverSetCurrent;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(NameserverSetCurrent::class)]
class NameserverSetCurrentTest extends IntegrationTestCase
{
    #[Test]
    public function nameserverRecordsAreSetToExternal(): void
    {
        $domain = 'test-domain.nl';
        $domainSubscription = DomainSubscriptionDataProvider::subscription($domain);
        $domainContact = new DomainContactFactory()->for($domainSubscription->customer)->createOne();

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $domainSubscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($domainSubscription->customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain($domain)
            ->parentSubscription($domainSubscription)
            ->createOne();

        $dnsDeployment = new DnsDeploymentFactory()->for($dnsSubscription)->createOne();

        $dnsHelperMock = self::createStub(DnsHelper::class);
        $dnsHelperMock
            ->method('dnsGetRecord')
            ->willReturn([
                ['target' => 'ns1.external-dns.com'],
                ['target' => 'ns2.external-dns.com'],
            ]);

        $dnsDeploymentRepository = self::resolve(DnsDeploymentRepository::class);
        $nameserverAssigner = self::resolve(DnsExternalNameserverAssigner::class);

        $job = new NameserverSetCurrent($domainSubscription);
        $job->handle($dnsDeploymentRepository, $dnsHelperMock, $nameserverAssigner);

        $dnsDeployment->refresh();

        self::assertSame(NameserverType::EXTERNAL, $dnsDeployment->nameserver_type);
        self::assertEqualsCanonicalizing(
            ['ns1.external-dns.com', 'ns2.external-dns.com'],
            $dnsDeployment->externalNameservers->pluck('nameserver')->toArray(),
        );
    }
}
