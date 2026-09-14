<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\Services\Microsoft365TenantService;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;

#[CoversClass(Microsoft365TenantService::class)]
class Microsoft365TenantServiceTest extends IntegrationTestCase
{
    private Microsoft365TenantService $microsoft365TenantService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->microsoft365TenantService = new Microsoft365TenantService(
            self::resolve(CartSerializerFactory::class),
        );
    }

    #[Test]
    public function getMicrosoft365MetaDataForNewTenant(): void
    {
        $tenantName = 'new-tenant';
        $json = sprintf('{"tenant_name":"%s","tenant_id":null,"type":"microsoft-365"}', $tenantName);
        $metaData = $this->microsoft365TenantService->getMicrosoft365MetaData($json);

        self::assertNull($metaData->tenantId);
        self::assertSame($tenantName, $metaData->tenantName);
    }

    #[Test]
    public function getMicrosoft365MetaDataForExistingTenant(): void
    {
        $tenantName = 'new-tenant.onmicrosoft.com';
        $tenantId = '1234';
        $json = sprintf('{"tenant_name":"%s","tenant_id":"%s","type":"microsoft-365"}', $tenantName, $tenantId);
        $metaData = $this->microsoft365TenantService->getMicrosoft365MetaData($json);

        self::assertNotNull($metaData->tenantId);
        self::assertSame($tenantId, $metaData->tenantId);
        self::assertSame($tenantName, $metaData->tenantName);
    }
}
