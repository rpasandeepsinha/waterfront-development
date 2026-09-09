<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DNS;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsRecordChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DnsRecordChangesController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsRecordChangesController::class)]
class DnsRecordChangesControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $product;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        //Customer who needs help
        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->dns()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->premiumDns($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOne();
    }

    #[Test]
    public function getDnsRecordChanges(): void
    {
        $dnsRecordChanges = new DnsRecordChangeFactory()->for($this->subscription)->createMany(2);
        new ProductSpecFactory()->for($this->product)->createOne(
            [
                'name' => ProductSpecName::DNS_VISIBLE_LOG_LINES->value,
                'value' => '10',
            ]
        );

        $translator = self::resolve(TranslatorInterface::class);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.dns.record-changes',
                    ['subscription' => $this->subscription->uuid]
                )
            );

        $response->assertOk();

        $expectedResponse = [];
        foreach ($dnsRecordChanges as $dnsRecordChange) {
            $expectedResponse[] = [
                'id' => $dnsRecordChange->id,
                'subscription_id' => $dnsRecordChange->subscription_id,
                'name' => $dnsRecordChange->name,
                'record_type' => $dnsRecordChange->record_type->value,
                'change_type' => $dnsRecordChange->change_type->value,
                'agent_type' => $translator->translate(
                    sprintf('dns.agent_type.%s', $dnsRecordChange->agent_type->value)
                ),
                'content' => $dnsRecordChange->content,
                'ttl' => $dnsRecordChange->ttl,
                'priority' => $dnsRecordChange->priority,
                'weight' => $dnsRecordChange->weight,
                'port' => $dnsRecordChange->port,
                'ip_address' => $dnsRecordChange->ip_address,
                'created_at' => $dnsRecordChange->created_at?->format(DateTimeFormat::DEFAULT),
                'updated_at' => $dnsRecordChange->updated_at?->format(DateTimeFormat::DEFAULT),
            ];
        }

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertJson($content);

        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('data', $decoded);
        self::assertEqualsCanonicalizing($expectedResponse, $decoded['data']);
    }

    #[Test]
    public function noDnsRecordChangesCreated(): void
    {
        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.dns.record-changes',
                    ['subscription' => $this->subscription->uuid]
                )
            );

        $response->assertOk();
        $response->assertExactJson(['data' => []]);
    }

    #[Test]
    public function noSpecSet(): void
    {
        new DnsRecordChangeFactory()->count(10)->for($this->subscription)->create();

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.dns.record-changes',
                    ['subscription' => $this->subscription->uuid]
                )
            );

        $response->assertOk();
        $response->assertExactJson(['data' => []]);
    }
}
