<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Integration;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Serializer\Serializer;
use Tests\IntegrationTestCase;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\CloudStackPaginationIterator;
use Waterfront\Infra\CloudStackClient\Mapper\ServiceOfferingMapper;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudstackService::class)]
class CloudstackServiceTest extends IntegrationTestCase
{
    private Environment $environment;

    private Serializer $serializer;

    public function setUp(): void
    {
        parent::setUp();

        Environment::unguard();
        $this->environment = Environment::create([
            'slug' => 'TEST01',
            'name' => 'Test Environment',
            'api_url' => 'https://api',
            'ui_url' => 'https://ui',
            'domain_id' => Str::uuid()->toString(),
            'domain_name' => 'test/vps',
            'preferred' => true,
            'default_email_address' => 'support@example.com',
            'default_role_id' => Str::uuid()->toString(),
            'api_key' => 'api-test-key',
            'secret_key' => 'secret-test-key',
        ]);
        Environment::reguard();

        $this->serializer = CloudstackSerializerFactory::get();
    }

    #[Test]
    public function getServiceOfferings(): void
    {
        $offeringid = '5bc9f753-7878-49e9-8165-31bbc9017830';
        $offeringName = 'Test versio-staging';

        $baseClient = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);

        $baseClient
            ->expects(self::once())
            ->method('execute')
            ->with('listServiceOfferings', [
                'domainid' => $this->environment->domain_id,
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'serviceoffering' => [
                    [
                        'id' => $offeringid,
                        'name' => $offeringName,
                        'memory' => 2048,
                        'cpunumber' => 2,
                        'domainid' => $this->environment->domain_id,
                        'domain' => 'vps',
                        'rootdisksize' => 20,
                    ],
                ],
            ]);

        $params = [
            'domainid' => $this->environment->domain_id,
        ];

        $iterator = new CloudStackPaginationIterator(
            client: $baseClient,
            command: 'listServiceOfferings',
            parameters: $params,
            type: 'serviceoffering',
            mapper: new ServiceOfferingMapper(),
        );

        $clientMock
            ->expects(self::once())
            ->method('listServiceOfferings')
            ->with($this->environment->domain_id)
            ->willReturn($iterator);

        $cloudstackService = new CloudstackService($clientAdminFactoryMock, $clientFactoryMock, $this->serializer);

        $offerings = $cloudstackService->getServiceOfferings(
            environmentId: $this->environment->id,
        );

        self::assertCount(1, $offerings);
        self::assertContains($offeringid, $offerings[0]);
        self::assertContains($offeringName, $offerings[0]);
    }

    #[Test]
    public function getServiceOfferingsNonFound(): void
    {
        $baseClient = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);

        $baseClient
            ->expects(self::once())
            ->method('execute')
            ->with('listServiceOfferings', [
                'domainid' => $this->environment->domain_id,
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 0,
                'serviceoffering' => [],
            ]);

        $params = [
            'domainid' => $this->environment->domain_id,
        ];

        $iterator = new CloudStackPaginationIterator(
            client: $baseClient,
            command: 'listServiceOfferings',
            parameters: $params,
            type: 'serviceoffering',
            mapper: new ServiceOfferingMapper(),
        );

        $clientMock
            ->expects(self::once())
            ->method('listServiceOfferings')
            ->with($this->environment->domain_id)
            ->willReturn($iterator);

        $cloudstackService = new CloudstackService($clientAdminFactoryMock, $clientFactoryMock, $this->serializer);

        $offerings = $cloudstackService->getServiceOfferings(
            environmentId: $this->environment->id,
        );

        self::assertCount(0, $offerings);
    }

    #[Test]
    public function getServiceOfferingsMissingEnvironment(): void
    {
        $clientAdminFactoryMock = self::createStub(AdminClientFactoryInterface::class);
        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);

        $cloudstackService = new CloudstackService($clientAdminFactoryMock, $clientFactoryMock, $this->serializer);

        $this->expectException(CloudstackException::class);
        $this->expectExceptionMessageIs('Environment not found');
        $cloudstackService->getServiceOfferings(
            environmentId: 1908,
        );
    }
}
