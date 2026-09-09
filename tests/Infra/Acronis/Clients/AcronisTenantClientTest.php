<?php

declare(strict_types=1);

namespace Tests\Infra\Acronis\Clients;

use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Contact;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Settings;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\AcronisClient\Enums\Tenants\CustomerType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ExternalOperationStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingCurrency;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\Tenant\CreateTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\DeleteTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantPricingSettingsRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantUsagesRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\PutTenantPricingSettingsRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\PutTenantRequest;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;

#[CoversClass(AcronisTenantClient::class)]
class AcronisTenantClientTest extends TestCase
{
    private const string TENANT_ID = 'f313ecf6-9256-4afd-9d47-72e032ee81d0';
    private const string PARENT_ID = 'fa6859a9-f5e1-4faf-a56c-5a0ae866dc4f';
    private const string UPDATE_LOCK_OWNER_ID = '7decff12-ee1b-4f8d-b446-59610bfb9203';
    private const string CONTACT_ID = '27f6f164-63dd-47df-b5b6-83a0fd117beb';
    private const int BRAND_ID = 1111;
    private const string EMAIL = 'test@sandwave.io';
    private const string PHONE = '123456789';
    private const string CONTACT_ADDRESS1 = 'Home';
    private const string LANGUAGE = 'en';
    private const int VERSION = 2;

    #[Test]
    public function getTenantSendsGetRequestAndReturnsDto(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/tenant.json');

        $mockClient = new OAuthMockClient([
            GetTenantRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenant = $tenantClient->get(self::TENANT_ID);

        $mockClient->assertSent(function (GetTenantRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s', self::TENANT_ID), $request->resolveEndpoint());
            self::assertSame([], $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuth

        $this->assertTenantResponseIsCorrect($tenant);
    }

    #[Test]
    public function getTenantWithQueryParams(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/tenant.json');

        $mockClient = new OAuthMockClient([
            GetTenantRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenant = $tenantClient->get(
            tenantId: self::TENANT_ID,
            embedPath: true,
            allowDeleted: true,
        );

        $mockClient->assertSent(function (GetTenantRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s', self::TENANT_ID), $request->resolveEndpoint());
            self::assertSame([
                'embed_path' => true,
                'allow_deleted' => true,
            ], $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient

        $this->assertTenantResponseIsCorrect($tenant);
    }

    #[Test]
    public function getTenantThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            GetTenantRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $tenantClient->get(self::TENANT_ID);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function createTenantSendsPostRequestAndSerializesUnsetVsNullCorrectly(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/tenant.json');

        $mockClient = new OAuthMockClient([
            CreateTenantRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_CREATED),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new Tenant(
            name: 'The Qwerty Tenant',
            parentId: self::PARENT_ID,
            kind: TenantType::CUSTOMER,
            contact: (function (): Contact {
                $contact = new Contact(email: self::EMAIL);
                $contact->address1 = self::CONTACT_ADDRESS1;
                $contact->phone = self::PHONE;
                return $contact;
            })(),
        );

        $payload->customerId = self::TENANT_ID;
        $payload->internalTag = null;
        $payload->language = self::LANGUAGE;
        $payload->settings = new Settings(enhancedSecurity: false);

        $tenant = $tenantClient->create($payload);

        $mockClient->assertSent(function (CreateTenantRequest $request): bool {
            self::assertSame('/tenants', $request->resolveEndpoint());

            $body = $request->body()->all();

            self::assertSame('The Qwerty Tenant', $body['name']);
            self::assertSame(self::PARENT_ID, $body['parent_id']);
            self::assertSame('customer', $body['kind']);
            self::assertIsArray($body['contact']);
            self::assertSame(self::EMAIL, $body['contact']['email']);
            self::assertSame(self::CONTACT_ADDRESS1, $body['contact']['address1']);
            self::assertSame(self::PHONE, $body['contact']['phone']);

            self::assertArrayNotHasKey('address2', $body['contact']);
            self::assertSame(self::TENANT_ID, $body['customer_id']);
            self::assertArrayHasKey('internal_tag', $body);
            self::assertNull($body['internal_tag']);
            self::assertSame(self::LANGUAGE, $body['language']);
            self::assertIsArray($body['settings']);
            self::assertFalse($body['settings']['enhanced_security']);
            self::assertArrayNotHasKey('default_idp_id', $body);
            self::assertArrayNotHasKey('enabled', $body);

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for Oauth
        $this->assertTenantResponseIsCorrect($tenant);
    }

    #[Test]
    public function createTenantThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            CreateTenantRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_CREATED),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new Tenant(
            name: 'The Qwerty Tenant',
            parentId: self::PARENT_ID,
            kind: TenantType::PARTNER,
            contact: new Contact(email: self::EMAIL),
        );

        self::expectException(AcronisSerializerException::class);

        $tenantClient->create($payload);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function updateTenantSendsPutRequestAndSerializesUnsetVsNullCorrectly(): void
    {
        $successResponse = file_get_contents(__DIR__ . '/../data/tenant.json');

        $mockClient = new OAuthMockClient([
            PutTenantRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new Tenant(
            name: 'Renamed Tenant',
            parentId: self::PARENT_ID,
            kind: TenantType::FOLDER,
            contact: (function (): Contact {
                $contact = new Contact(email: self::EMAIL);
                $contact->firstname = 'New Name';
                $contact->lastname = null;
                return $contact;
            })(),
            version: self::VERSION
        );

        $payload->brandId = self::BRAND_ID;
        $payload->language = self::LANGUAGE;
        $payload->customerType = CustomerType::ENTERPRISE;

        $payload->customerId = null;
        $payload->internalTag = null;
        $payload->externalOperationStatus = ExternalOperationStatus::NO_OPERATION;

        $tenant = $tenantClient->update(self::TENANT_ID, $payload);

        $mockClient->assertSent(function (PutTenantRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s', self::TENANT_ID), $request->resolveEndpoint());

            $body = $request->body()->all();

            self::assertSame(self::VERSION, $body['version']);

            self::assertSame('Renamed Tenant', $body['name']);
            self::assertSame(self::BRAND_ID, $body['brand_id']);
            self::assertArrayNotHasKey('parent_id', $body);
            self::assertArrayNotHasKey('external_operation_status', $body);
            self::assertSame(TenantType::FOLDER->value, $body['kind']);
            self::assertSame(self::LANGUAGE, $body['language']);
            self::assertSame(CustomerType::ENTERPRISE->value, $body['customer_type']);

            self::assertArrayHasKey('customer_id', $body);
            self::assertNull($body['customer_id']);

            self::assertArrayHasKey('internal_tag', $body);
            self::assertNull($body['internal_tag']);

            self::assertArrayNotHasKey('enabled', $body);
            self::assertArrayNotHasKey('default_idp_id', $body);

            self::assertIsArray($body['contact']);
            self::assertSame('New Name', $body['contact']['firstname']);

            self::assertArrayHasKey('lastname', $body['contact']);
            self::assertNull($body['contact']['lastname']);

            self::assertArrayNotHasKey('phone', $body['contact']);

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
        $this->assertTenantResponseIsCorrect($tenant);
    }

    #[Test]
    public function updateTenantThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            PutTenantRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new Tenant(
            name: 'New Name',
            parentId: self::PARENT_ID,
            kind: TenantType::CUSTOMER,
            contact: null,
            version: self::VERSION,
        );

        self::expectException(AcronisSerializerException::class);

        $tenantClient->update(self::TENANT_ID, $payload);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function updatePricingSettingsSuccess(): void
    {
        $response = (string) file_get_contents(__DIR__ . '/../data/tenant-price-settings.json');
        $mockClient = new OAuthMockClient([
            PutTenantPricingSettingsRequest::class => MockResponse::make(body: $response, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new TenantPricingSettings(version: self::VERSION, mode: PricingMode::PRODUCTION, currency: PricingCurrency::EUR);

        $tenantPricingSettings = $tenantClient->updatePricingSettings(self::TENANT_ID, $payload);

        // These asserts match the json
        self::assertSame(PricingCurrency::EUR, $tenantPricingSettings->currency);
        self::assertSame(PricingMode::PRODUCTION, $tenantPricingSettings->mode);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function updatePricingSettingsThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            PutTenantPricingSettingsRequest::class => MockResponse::make(body: '{"version": "string-not-int"}', status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $payload = new TenantPricingSettings(version: self::VERSION, mode: PricingMode::PRODUCTION, currency: PricingCurrency::EUR);

        self::expectException(AcronisSerializerException::class);

        $tenantClient->updatePricingSettings(self::TENANT_ID, $payload);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function getPricingSettingsSuccess(): void
    {
        $response = (string) file_get_contents(__DIR__ . '/../data/tenant-price-settings.json');
        $mockClient = new OAuthMockClient([
            GetTenantPricingSettingsRequest::class => MockResponse::make(body: $response, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenantPricingSettings = $tenantClient->getPricingSettings(self::TENANT_ID);

        // These asserts match the json
        self::assertSame(PricingCurrency::EUR, $tenantPricingSettings->currency);
        self::assertSame(PricingMode::PRODUCTION, $tenantPricingSettings->mode);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function getPricingSettingsThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            GetTenantPricingSettingsRequest::class => MockResponse::make(body: '{"version": "should be int"}', status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $tenantClient->getPricingSettings(self::TENANT_ID);

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function deleteTenantSendsDeleteRequestWithVersion(): void
    {
        $mockClient = new OAuthMockClient([
            DeleteTenantRequest::class => MockResponse::make(status: Response::HTTP_NO_CONTENT),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenantClient->delete(self::TENANT_ID, self::VERSION);

        $mockClient->assertSent(function (DeleteTenantRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s', self::TENANT_ID), $request->resolveEndpoint());

            $query = $request->query()->all();
            self::assertSame(['version' => self::VERSION], $query);

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuthClient
    }

    #[Test]
    public function getTenantUsagesSuccess(): void
    {
        $successResponse = (string) file_get_contents(__DIR__ . '/../data/tenant-usages.json');

        $mockClient = new OAuthMockClient([
            GetTenantUsagesRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenantUsages = $tenantClient->getTenantUsages(self::TENANT_ID);

        $mockClient->assertSent(function (GetTenantUsagesRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s/usages', self::TENANT_ID), $request->resolveEndpoint());
            self::assertSame([], $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuth

        self::assertCount(3, $tenantUsages->items);
        $first = $tenantUsages->items[0];
        self::assertSame('c6ab7fb4-b461-4214-9257-86fbad8efb85', $first->applicationId);
        self::assertSame('adv_vms', $first->name);
        self::assertSame('advanced', $first->edition);
        self::assertSame(UsageName::VMS, $first->usageName);
        self::assertSame(OfferingItemType::COUNT, $first->type);
        self::assertSame(MeasurementUnit::QUANTITY, $first->measurementUnit);
        self::assertSame('2017-06-01T00:00:00', $first->rangeStart);
        self::assertSame(800, $first->absoluteValue);
        self::assertSame(800, $first->value);
        self::assertNull($first->infraId);

        self::assertNotNull($first->offeringItem);
        self::assertSame(1, $first->offeringItem->status->value);

        $firstQuota = $first->offeringItem->quota;
        self::assertNotNull($firstQuota);
        self::assertSame(1486479690324, $firstQuota->version);
        self::assertSame(10, $firstQuota->value);
        self::assertSame(10, $firstQuota->overage);

        $second = $tenantUsages->items[1];
        self::assertSame('c6ab7fb4-b461-4214-9257-86fbad8efb85', $second->applicationId);
        self::assertSame('storage', $second->name);
        self::assertSame('standard', $second->edition);
        self::assertSame(UsageName::STORAGE, $second->usageName);
        self::assertSame(OfferingItemType::INFRA, $second->type);
        self::assertSame(MeasurementUnit::BYTES, $second->measurementUnit);
        self::assertSame('df476eec-c470-40b0-9b10-37223b7f4a2b', $second->infraId);
        self::assertSame('2017-06-22T00:00:00', $second->rangeStart);
        self::assertSame(800, $second->absoluteValue);
        self::assertSame(800, $second->value);

        self::assertNotNull($second->offeringItem);
        self::assertSame(1, $second->offeringItem->status->value);

        $secondQuota = $second->offeringItem->quota;
        self::assertNotNull($secondQuota);
        self::assertSame(1486479690324, $secondQuota->version);
        self::assertSame(1000, $secondQuota->value);
        self::assertSame(2000, $secondQuota->overage);

        $third = $tenantUsages->items[2];
        self::assertSame('c6ab7fb4-b461-4214-9257-86fbad8efb85', $third->applicationId);
        self::assertSame('vm_storage', $third->name);
        self::assertSame('standard', $third->edition);
        self::assertSame(UsageName::VM_STORAGE, $third->usageName);
        self::assertSame(OfferingItemType::INFRA, $third->type);
        self::assertSame(MeasurementUnit::BYTES, $third->measurementUnit);
        self::assertSame('2017-06-01T00:00:00', $third->rangeStart);
        self::assertSame(100000, $third->absoluteValue);
        self::assertSame(100000, $third->value);
        self::assertNull($third->infraId);
        self::assertNull($third->offeringItem);
    }

    #[Test]
    public function getTenantUsagesWithQueryParams(): void
    {
        $successResponse = (string) file_get_contents(__DIR__ . '/../data/tenant-usages.json');

        $mockClient = new OAuthMockClient([
            GetTenantUsagesRequest::class => MockResponse::make(body: $successResponse, status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        $tenantClient->getTenantUsages(
            tenantId: self::TENANT_ID,
            usageNames: 'storage,local_storage',
            editions: 'standard,advanced',
        );

        $mockClient->assertSent(function (GetTenantUsagesRequest $request): bool {
            self::assertSame(sprintf('/tenants/%s/usages', self::TENANT_ID), $request->resolveEndpoint());
            self::assertSame([
                'usage_names' => 'storage,local_storage',
                'editions' => 'standard,advanced',
            ], $request->query()->all());

            return true;
        });

        $mockClient->assertSentCount(2); // 1 for OAuth
    }

    #[Test]
    public function getTenantUsagesThrowsAcronisSerializerException(): void
    {
        $mockClient = new OAuthMockClient([
            GetTenantUsagesRequest::class => MockResponse::make(body: '{}', status: Response::HTTP_OK),
        ]);

        $tenantClient = $this->makeClient($mockClient);

        self::expectException(AcronisSerializerException::class);

        $tenantClient->getTenantUsages(self::TENANT_ID);

        $mockClient->assertSentCount(2); // 1 for OAuth
    }

    private function assertTenantResponseIsCorrect(Tenant $tenant): void
    {
        self::assertSame(self::TENANT_ID, $tenant->id);
        self::assertSame('The Qwerty Tenant', $tenant->name);
        self::assertSame(2, $tenant->version);

        self::assertSame(self::PARENT_ID, $tenant->parentId);
        self::assertTrue($tenant->enabled);

        self::assertSame('2016-06-22T18:25:16', $tenant->createdAt);
        self::assertSame('2016-06-22T18:25:16', $tenant->updatedAt);
        self::assertNull($tenant->deletedAt);

        self::assertSame(1, $tenant->brandId);
        self::assertNull($tenant->internalTag);
        self::assertSame('en', $tenant->language);
        self::assertTrue($tenant->hasChildren);
        self::assertTrue($tenant->ancestralAccess);

        self::assertNotNull($tenant->updateLock);
        self::assertTrue($tenant->updateLock->enabled);
        self::assertSame(self::UPDATE_LOCK_OWNER_ID, $tenant->updateLock->ownerId);

        self::assertNotNull($tenant->contact);
        self::assertSame(self::CONTACT_ID, $tenant->contact->id);
        self::assertSame(self::EMAIL, $tenant->contact->email);

        self::assertCount(0, $tenant->contacts);
    }

    private function makeClient(OAuthMockClient $mockClient): AcronisTenantClient
    {
        $connector = new AcronisConnector(
            acronisConfig: $this->makeConnectorConfig(),
            logger: self::createStub(LoggerInterface::class),
            logMasker: self::createStub(JsonLogMasker::class),
            cache: self::createStub(Repository::class),
        );

        $connector->withMockClient($mockClient);

        return new AcronisTenantClient($connector);
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
