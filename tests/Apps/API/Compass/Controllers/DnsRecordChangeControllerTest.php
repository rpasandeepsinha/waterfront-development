<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsRecordChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DnsRecordChangeController;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsRecordChangeController::class)]
class DnsRecordChangeControllerTest extends IntegrationTestCase
{
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->dns()->createOne();
        $product = new ProductFactory()->for($productGroup)->premiumDns($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();
    }

    #[Test]
    public function getDnsRecordChanges(): void
    {
        $dnsRecordChanges = new DnsRecordChangeFactory()->for($this->subscription)->createMany(2);
        $translator = self::resolve(TranslatorInterface::class);

        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute(
                    'admin.dns.dns_record_change',
                    ['domain' => $this->subscription->domain]
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
                'changed_by_metadata' => $dnsRecordChange->changed_by_metadata,
                'changed_by_uuid' => $dnsRecordChange->changed_by_uuid,
                'event' => $translator->translate('dns.record.changed'),
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
        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute(
                    'admin.dns.dns_record_change',
                    ['domain' => $this->subscription->domain]
                )
            );
        $response->assertOk();
        $response->assertJsonFragment(['data' => []]);
        $response->assertJsonFragment(['per_page' => 100, 'to' => null, 'total' => 0, 'totalLogs' => 0]);
    }
}
