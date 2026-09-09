<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Live;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(RtrService::class)]
class RtrServiceTest extends IntegrationTestCase
{
    //It doens't test register, modify and transfer cause that would be risky for the test api.
    //And modify handle otherwise we can't login anymore.

    private string $domainNl = 'sandwave-test.nl';

    private DomainDeployment $domainDeployment;

    private RtrService $rtrService;

    public function setUp(): void
    {
        parent::setUp();
        $this->rtrService = self::resolve(RtrService::class);

        $customer = new CustomerFactory()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'slug' => 'openprovider',
        ]);

        $groupExtension = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);

        $productNl = new ProductFactory()->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'product_group_id' => $groupExtension->id,
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain' => $this->domainNl,
            'customer_id' => $customer->id,
            'product_uuid' => $productNl->uuid,
        ]);

        $this->domainDeployment = new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
    }

    #[Test]
    public function check(): void
    {
        $realtimeRegisterClient = $this->setUpRtrClient();

        $result = $this->rtrService->setClient($realtimeRegisterClient)->check('testcertificaat.com');

        self::assertSame(CheckResult::STATUS_FREE, $result->getStatus());
    }

    #[Test]
    public function isDnssecSupported(): void
    {
        $realtimeRegisterClient = $this->setUpRtrClient();

        $result = $this->rtrService->setClient($realtimeRegisterClient)->isDnssecSupported($this->domainNl);
        self::assertTrue($result, 'failed asserting dnssec is supported');
    }

    #[Test]
    public function nameservers(): void
    {
        $realtimeRegister = $this->setUpRtrClient();

        $nameservers = $this->rtrService->setClient($realtimeRegister)->nameservers($this->domainDeployment)->getNameServers();
        self::assertNotEmpty($nameservers, 'Failed asserting that domain has nameservers');
    }

    #[Test]
    public function retrieveRenewalDate(): void
    {
        $realtimeRegister = $this->setUpRtrClient();

        $renewalDate = $this->rtrService->setClient($realtimeRegister)->retrieveRenewalDate($this->domainNl);

        self::assertTrue($renewalDate->isFuture());
    }

    #[Test]
    public function retrieveCustomerHandle(): void
    {
        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($this->setUpRtrClient())->retrieveCustomerHandle($handle);

        self::assertSame('sandwave-ote1', $result->getHandle());
    }

    #[Test]
    public function isDefaultNameservers(): void
    {
        $retrieveResult = $this->rtrService->setClient($this->setUpRtrClient())->nameservers($this->domainDeployment);

        self::assertTrue(
            $retrieveResult->getIsDefaultNameservers(),
            'Default nameserver should be set. Did you set the PRIMARY_NAMESERVER=ns01.example.com in your test env?'
        );
    }

    #[Test]
    public function retrieveDnssecKeys(): void
    {
        $result = $this->rtrService->setClient($this->setUpRtrClient())
            ->retrieveDnssecKeys($this->domainNl);

        self::assertSame([
            0 => include __DIR__ . '/data/key_data_valid.php',
            1 => include __DIR__ . '/data/key_data_valid.php',
        ], $result);
    }

    private function setUpRtrClient(): RealtimeRegister
    {
        $key = $this->getConfiguration()->getAsString('realtimeregisterclient.connection.api_key');
        $url = $this->getConfiguration()->getAsString('realtimeregisterclient.connection.api_url');
        return new RealtimeRegister($key, $url);
    }
}
