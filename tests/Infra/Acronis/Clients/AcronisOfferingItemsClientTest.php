<?php

declare(strict_types=1);

namespace Tests\Infra\Acronis\Clients;

use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsFilter;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsPricing;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsPricingItem;
use Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems\OfferingItemsPricing as OfferingItemsPricingResponse;
use Waterfront\Infra\AcronisClient\Enums\Application;
use Waterfront\Infra\AcronisClient\Enums\Infrastructure;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\GetOfferingItemsPricingRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\GetOfferingItemsRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\PutOfferingItemsPricingRequest;
use Waterfront\Infra\AcronisClient\Requests\OfferingItems\PutOfferingItemsRequest;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;

#[CoversClass(AcronisOfferingItemsClient::class)]
class AcronisOfferingItemsClientTest extends TestCase
{
    private const string TENANT_ID = 'f313ecf6-9256-4afd-9d47-72e032ee81d0';
    private const string INFRA_ID = Infrastructure::RECOVERY1->value;
    private const string APPLICATION_ID = Application::CYBER_PROTECTION->value;
    private const int VERSION = 1497533157730;

    #[Test]
    public function getOfferingItemsWithDefaultFilter(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/offering-items.json');

        $mockClient = new OAuthMockClient([
            GetOfferingItemsRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        $offeringItems = $offeringItemsClient->get(self::TENANT_ID);

        $mockClient->assertSent(function (GetOfferingItemsRequest $request): bool {
            self::assertSame(
                sprintf('/tenants/%s/offering_items', self::TENANT_ID),
                $request->resolveEndpoint()
            );

            self::assertSame(['edition' => '*'], $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertOfferingItemsIsCorrect($offeringItems);
    }

    #[Test]
    public function getOfferingItemsWithCustomFilter(): void
    {
        $filter = new OfferingItemsFilter(
            edition: '*',
            forUi: true,
            usageNames: ['storage', 'local_storage'],
            availableOnly: true,
            type: OfferingItemType::INFRA,
            status: OfferingItemStatus::ACTIVE,
            infraUuid: self::INFRA_ID,
            includeSecondary: true,
        );

        $successResponse = file_get_contents(__DIR__ . '/../data/offering-items.json');

        $mockClient = new OAuthMockClient([
            GetOfferingItemsRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $offeringitemsclient = $this->makeClient($mockClient);

        $offeringItems = $offeringitemsclient->get(self::TENANT_ID, $filter);
        $expectedQuery = $filter->toQuery();

        $mockClient->assertSent(function (GetOfferingItemsRequest $request) use ($expectedQuery): bool {
            self::assertSame(
                sprintf('/tenants/%s/offering_items', self::TENANT_ID),
                $request->resolveEndpoint()
            );

            self::assertSame($expectedQuery, $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertOfferingItemsIsCorrect($offeringItems);
    }

    #[Test]
    public function getOfferingItemsThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            GetOfferingItemsRequest::class => MockResponse::make(body: '{"items": "invalid"}', status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $offeringItemsClient->get(self::TENANT_ID);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function updateOfferingItemsSendsPutRequestAndSerializesUnsetVsNullCorrectly(): void
    {
        $item0 = new OfferingItem(
            applicationId: self::APPLICATION_ID,
            name: 'pg_base_vms',
            tenantId: Uuid::uuid4()->toString(),
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: Quota::limited(value: 10, overage: 10),
        );

        $item1 = new OfferingItem(
            applicationId: self::APPLICATION_ID,
            name: 'pg_base_adv_dr_storage',
            tenantId: Uuid::uuid4()->toString(),
            status: OfferingItemStatus::NOT_ACTIVE,
            infraId: self::INFRA_ID,
            quota: null,
        );

        $payload = new OfferingItems(checkUsage: true, offeringItems: [$item0, $item1]);

        $successResponse = file_get_contents(__DIR__ . '/../data/offering-items.json');

        $mockClient = new OAuthMockClient([
            PutOfferingItemsRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        $offeringItems = $offeringItemsClient->update(self::TENANT_ID, $payload);

        $mockClient->assertSent(function (PutOfferingItemsRequest $request) use ($payload): bool {
            self::assertSame(
                sprintf('/tenants/%s/offering_items', self::TENANT_ID),
                $request->resolveEndpoint()
            );

            $body = $request->body()->all();

            self::assertTrue($body['check_usage']);
            self::assertIsArray($body['offering_items']);
            self::assertArrayNotHasKey('items', $body);
            self::assertArrayNotHasKey('timestamp', $body);
            self::assertArrayNotHasKey('paging', $body);
            self::assertNotNull($payload->offeringItems);
            self::assertCount(count($payload->offeringItems), $body['offering_items']);

            /** @var list<array<string, mixed>> $items */
            $items = $body['offering_items'];

            $dto0 = $items[0];
            self::assertSame(self::APPLICATION_ID, $dto0['application_id']);
            self::assertSame('pg_base_vms', $dto0['name']);
            self::assertSame(OfferingItemStatus::ACTIVE->value, $dto0['status']);

            self::assertArrayHasKey('infra_id', $dto0);
            self::assertNull($dto0['infra_id']);

            self::assertIsArray($dto0['quota']);
            self::assertSame(10, $dto0['quota']['value']);
            self::assertSame(10, $dto0['quota']['overage']);
            self::assertArrayHasKey('version', $dto0['quota']);

            $dto1 = $items[1];
            self::assertSame(self::APPLICATION_ID, $dto1['application_id']);
            self::assertSame('pg_base_adv_dr_storage', $dto1['name']);
            self::assertSame(OfferingItemStatus::NOT_ACTIVE->value, $dto1['status']);
            self::assertSame(self::INFRA_ID, $dto1['infra_id']);
            self::assertArrayHasKey('quota', $dto1);
            self::assertEmpty($dto1['quota']);

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertOfferingItemsIsCorrect($offeringItems);
    }

    #[Test]
    public function updateOfferingItemsThrowsAcronisSerializerException(): void
    {
        $item0 = new OfferingItem(
            applicationId: self::APPLICATION_ID,
            name: 'pg_base_vms',
            tenantId: Uuid::uuid4()->toString(),
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: Quota::limited(value: 10, overage: 10),
        );

        $payload = new OfferingItems(checkUsage: true, offeringItems: [$item0]);

        $mockClient = new OAuthMockClient([
            PutOfferingItemsRequest::class => MockResponse::make(body: '{"items": "invalid"}', status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $offeringItemsClient->update(self::TENANT_ID, $payload);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function getOfferingItemsPricingSendsGetRequestAndReturnsDto(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/offering-items-pricing.json');

        $mockClient = new OAuthMockClient([
            GetOfferingItemsPricingRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        $pricing = $offeringItemsClient->getPricing(self::TENANT_ID);

        $mockClient->assertSent(function (GetOfferingItemsPricingRequest $request): bool {
            self::assertSame(
                sprintf('/tenants/%s/offering_items/pricing', self::TENANT_ID),
                $request->resolveEndpoint()
            );

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertOfferingItemsPricingResponseIsCorrect($pricing);
    }

    #[Test]
    public function getOfferingItemsPricingThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            GetOfferingItemsPricingRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $offeringItemsClient->getPricing(self::TENANT_ID);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function updateOfferingItemsPricingSendsPutRequestAndSerializesUnsetVsNullCorrectly(): void
    {
        $item0 = new OfferingItemsPricingItem(
            applicationId: self::APPLICATION_ID,
            name: 'storage',
            price: '0.05',
            version: self::VERSION,
        );
        $item0->infraId = self::INFRA_ID;

        $item1 = new OfferingItemsPricingItem(
            applicationId: self::APPLICATION_ID,
            name: 'servers',
            price: '600',
            version: self::VERSION,
        );

        $payload = new OfferingItemsPricing(items: [$item0, $item1]);

        $successResponse = file_get_contents(__DIR__ . '/../data/offering-items-pricing.json');

        $mockClient = new OAuthMockClient([
            PutOfferingItemsPricingRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        $pricing = $offeringItemsClient->updatePricing(self::TENANT_ID, $payload);

        $mockClient->assertSent(function (PutOfferingItemsPricingRequest $request) use ($payload): bool {
            self::assertSame(
                sprintf('/tenants/%s/offering_items/pricing', self::TENANT_ID),
                $request->resolveEndpoint()
            );

            $body = $request->body()->all();

            self::assertIsArray($body['items']);

            /** @var list<array<string, mixed>> $items */
            $items = $body['items'];

            self::assertCount(count($payload->items), $items);

            $dto0 = $items[0];
            self::assertSame(self::APPLICATION_ID, $dto0['application_id']);
            self::assertSame('storage', $dto0['name']);
            self::assertSame(self::INFRA_ID, $dto0['infra_id']);
            self::assertSame('0.05', $dto0['price']);
            self::assertSame(self::VERSION, $dto0['version']);

            $dto1 = $items[1];
            self::assertSame(self::APPLICATION_ID, $dto1['application_id']);
            self::assertSame('servers', $dto1['name']);
            self::assertArrayNotHasKey('infra_id', $dto1);
            self::assertSame('600', $dto1['price']);
            self::assertSame(self::VERSION, $dto1['version']);

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertOfferingItemsPricingResponseIsCorrect($pricing);
    }

    #[Test]
    public function updateOfferingItemsPricingThrowsAcronisSerializerException(): void
    {
        $item0 = new OfferingItemsPricingItem(
            applicationId: self::APPLICATION_ID,
            name: 'servers',
            price: '600',
            version: self::VERSION,
        );

        $payload = new OfferingItemsPricing(items: [$item0]);

        $mockClient = new OAuthMockClient([
            PutOfferingItemsPricingRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_OK),
        ]);

        $offeringItemsClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $offeringItemsClient->updatePricing(self::TENANT_ID, $payload);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    private function assertOfferingItemsIsCorrect(OfferingItems $offeringItems): void
    {
        self::assertSame('2016-06-22T18:25:16', $offeringItems->timestamp);
        self::assertNotNull($offeringItems->items);
        self::assertCount(4, $offeringItems->items);
        self::assertNull($offeringItems->checkUsage);
        self::assertNull($offeringItems->offeringItems);

        foreach ($offeringItems->items as $item) {
            self::assertNotEmpty($item->applicationId);
            self::assertNotEmpty($item->name);
            self::assertNotEmpty($item->usageName);
            self::assertSame(self::TENANT_ID, $item->tenantId);
        }

        $first = $offeringItems->items[0];
        self::assertSame(self::APPLICATION_ID, $first->applicationId);
        self::assertSame('vms', $first->name);
        self::assertSame('standard', $first->edition);
        self::assertSame('vms', $first->usageName);
        self::assertSame(self::TENANT_ID, $first->tenantId);
        self::assertSame('2016-06-22T18:25:16', $first->updatedAt);
        self::assertNull($first->deletedAt);
        self::assertSame(OfferingItemStatus::ACTIVE, $first->status);
        self::assertTrue($first->locked);
        self::assertSame(OfferingItemType::COUNT, $first->type);
        self::assertSame(MeasurementUnit::QUANTITY, $first->measurementUnit);
        self::assertNotNull($first->quota);
        self::assertSame(10, $first->quota->value);
        self::assertSame(10, $first->quota->overage);
        self::assertSame(1486479690324, $first->quota->version);

        $infra = $offeringItems->items[3];
        self::assertSame(OfferingItemType::INFRA, $infra->type);
        self::assertSame(self::INFRA_ID, $infra->infraId);
        self::assertNotNull($infra->quota);
        self::assertSame(1000, $infra->quota->value);
        self::assertNull($infra->quota->overage);
    }

    private function assertOfferingItemsPricingResponseIsCorrect(OfferingItemsPricingResponse $pricing): void
    {
        self::assertCount(2, $pricing->items);

        $first = $pricing->items[0];
        self::assertSame(self::APPLICATION_ID, $first->applicationId);
        self::assertSame('storage', $first->name);
        self::assertSame(self::INFRA_ID, $first->infraId);
        self::assertSame('0.05', $first->price);
        self::assertSame(1497533157730, $first->version);

        $second = $pricing->items[1];
        self::assertSame(self::APPLICATION_ID, $second->applicationId);
        self::assertSame('servers', $second->name);
        self::assertNull($second->infraId);
        self::assertSame('600', $second->price);
        self::assertSame(1497533157730, $second->version);
    }

    private function makeClient(MockClient $mockClient): AcronisOfferingItemsClient
    {
        $connector = new AcronisConnector(
            acronisConfig: $this->makeConnectorConfig(),
            logger: self::createStub(LoggerInterface::class),
            logMasker: self::createStub(JsonLogMasker::class),
            cache: self::createStub(Repository::class),
        );

        $connector->withMockClient($mockClient);

        return new AcronisOfferingItemsClient($connector);
    }

    private function makeConnectorConfig(): ConnectorConfig
    {
        return new ConnectorConfig(
            baseUrl: 'https://acronis.test/api/2',
            clientId: self::TENANT_ID,
            clientSecret: 'abc123',
            retryConfig: new RetryConfig(),
        );
    }
}
