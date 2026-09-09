<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\DNS\Services\Templates\TemplateService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(TemplateService::class)]
class DnsCustomerTemplateServiceTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    protected Customer $customer;

    protected TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne();
        $this->templateService = self::resolve(TemplateService::class);

        $mockDnsLogService = self::createStub(DnsLogService::class);
        $this->app->bind(DnsLogService::class, fn (): DnsLogService => $mockDnsLogService);
    }

    #[Test]
    public function findOrCreateNew(): void
    {
        $records = $this->getExampleRecords();
        $payload = $this->generateTemplatePayload($this->customer, 'exampleTemplate', $records);

        self::assertDatabaseMissing('dns_customer_templates', ['name' => 'exampleTemplate']);

        $this->templateService->findOrCreateTemplate($this->customer, $payload);

        self::assertDatabaseHas('dns_customer_templates', ['name' => 'exampleTemplate']);
        self::assertDatabaseHas('dns_customer_template_records', [
            'name' => 'test.@',
            'content' => '127.0.0.1',
            'type' => 'A',
            'ttl' => 3600,
            'disabled' => false,
        ]);
    }

    #[Test]
    public function findOrCreateExisting(): void
    {
        $records = $this->getExampleRecords();

        $originalTemplate = DnsCustomerTemplate::create([
            'name' => 'exampleExistingTemplate',
            'customer_id' => $this->customer->id,
        ]);
        $originalTemplate->refresh();

        self::assertDatabaseHas('dns_customer_templates', ['name' => 'exampleExistingTemplate']);

        $payload = $this->generateTemplatePayload($this->customer, 'exampleExistingTemplate', $records);

        $template = $this->templateService->findOrCreateTemplate($this->customer, $payload);

        self::assertSame(
            $originalTemplate->toArray(),
            $template->toArray(),
            'The array payload of the created template did not equal the payload of the model return form the DnsTemplateService class'
        );
    }

    #[Test]
    public function applyTemplateToZone(): void
    {
        $this->templatePdnsMock();

        $records = $this->getExampleRecords();
        $domain = 'domain.com';
        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $domainGroup   =  new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $product      = new ProductFactory()->for($domainGroup)->createOne([
            'name' => 'DNS Templates',
            'slug' => 'dns_templates',
        ]);
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid'       => $product->uuid,
            'domain'             => 'domain.com',
        ]);
        $domainDeployment = DomainDeployment::create([
            'subscription_uuid' => $subscription->uuid,
            'provider_id'       => $provider->id,
        ]);

        $this->customer->subscriptions()->save($subscription);
        $subscription->domainDeployment()->save($domainDeployment);
        $subscription->save();
        $payload = $this->generateTemplatePayload($this->customer, 'exampleTemplate', $records);

        self::assertDatabaseMissing('dns_customer_templates', ['name' => 'exampleTemplate']);

        $template = $this->templateService->findOrCreateTemplate($this->customer, $payload);

        $appendable = [
            'name' => 'updated.domain.com',
            'content' => '127.0.0.1',
            'type' => 'A',
            'ttl' => 999,
            'disabled' => false,
        ];

        $extraRecord = new DnsCustomerTemplateRecord($appendable);

        $template->records()->save($extraRecord);

        self::resolve(TemplateService::class)->applyTemplateToZone($template->refresh(), $domain);
    }

    #[Test]
    public function updateTemplate(): void
    {
        $records = $this->getExampleRecords();

        $originalIp = '127.0.0.1';
        $changedIp  = '192.168.2.2';

        $originalTemplate = DnsCustomerTemplate::create([
            'name' => 'exampleExistingTemplate',
            'customer_id' => $this->customer->id,
        ]);

        $checkArray = $originalTemplate->toArray();

        $records[0]['content'] = $changedIp;

        self::assertDatabaseHas('dns_customer_templates', ['name' => 'exampleExistingTemplate']);

        $payload = $this->generateTemplatePayload($this->customer, 'different_name', $records);

        $updatedTemplate = $this->templateService->updateTemplate($originalTemplate, $payload);

        self::assertNotEquals($checkArray, $updatedTemplate->toArray());
        self::assertDatabaseMissing('dns_customer_template_records', ['content' => $originalIp]);
    }

    #[Test]
    public function removeTemplateFromZone(): void
    {
        $domain = 'domain.com';

        $originalTemplate = DnsCustomerTemplate::create([
            'name' => 'exampleExistingTemplate',
            'customer_id' => $this->customer->id,
        ]);

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $product      = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'DNS Templates',
            'slug' => 'dns_templates',
        ]);
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid'       => $product->uuid,
            'domain'             => $domain,
        ]);

        $domainDeployment = DomainDeployment::create([
            'subscription_uuid' => $subscription->uuid,
            'template_id'       => $originalTemplate->id,
            'provider_id'       => $provider->id,
        ]);

        $this->customer->subscriptions()->save($subscription);
        $subscription->save();
        $subscription->domainDeployment()->save($domainDeployment);

        $this->templateService->removeTemplateFromZone($domain);

        $domainDeployment->refresh();
        self::assertNull($domainDeployment->template_id);
    }

    #[Test]
    public function getPdnsZonesForDomains(): void
    {
        $this->templatePdnsMock();

        $domains = [
            ['domain' => 'domain.com'],
            ['domain' => 'domain2.com'],
        ];

        $collection = self::resolve(TemplateService::class)->getPdnsZonesForDomains($domains);

        self::assertCount(2, $collection);

        self::assertSame(
            Arr::flatten($domains),
            $collection->map(fn (array $domain) => Arr::get($domain, 'domain'))->toArray()
        );
    }

    #[Test]
    public function getPdnsZonesForDomainsPdnsQueryNotFound(): void
    {
        $this->pdnsMockwithErrorNotFound();
        $domains = [
            ['domain' => 'domain.com'],
            ['domain' => 'danger.com'],
        ];

        $this->expectException(DnsZoneNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Zone not found!');
        self::resolve(TemplateService::class)->getPdnsZonesForDomains($domains);
    }

    #[Test]
    public function getPdnsZonesForDomainsPdnsQueeryNotFound(): void
    {
        $this->pdnsMockwithErrorNotFound();

        $domains = [
            ['domain' => 'domain.com'],
            ['domain' => 'danger.com'],
        ];

        $this->expectException(DnsZoneNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Zone not found!');
        self::resolve(TemplateService::class)->getPdnsZonesForDomains($domains);
    }

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    private function getExampleRecords(): array
    {
        return [
            [
                'name' => 'test.@',
                'content' => '127.0.0.1',
                'type' => 'A',
                'ttl' => 3600,
                'disabled' => false,
            ],
            [
                'name' => 'domain.com',
                'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
                'priority' => 20,
                'type' => 'MX',
                'ttl' => 600,
            ],
        ];
    }

    /**
     * @param array<int, array<string, bool|int|string>> $records
     *
     * @return array<string, array<int, array<string, bool|int|string>>|int|string>
     */
    private function generateTemplatePayload(Customer $customer, string $name, array $records): array
    {
        return [
            'customer_id' => $customer->id,
            'name' => $name,
            'records' => $records,
        ];
    }

    private function pdnsMockwithErrorNotFound(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                404,
                [],
                'Zone not found!'
            ),
        ]);

        $this->pdns($pdns);
    }

    private function templatePdnsMock(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test.com')
            ),
        ]);

        $this->pdns($pdns);
    }
}
