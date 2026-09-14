<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Controllers\Microsoft365Controller;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(Microsoft365Controller::class)]
#[AllowMockObjectsWithoutExpectations]
class Microsoft365ControllerTest extends IntegrationTestCase
{
    public const string TENANT = 'tenant';
    public const string TENANT_ID = '11111111-1111-1111-1111-111111111111';

    private Customer $customer;

    private Microsoft365Service&MockObject $microsoft365Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->microsoft365Service = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $this->microsoft365Service);
    }

    #[Test]
    public function tenantCheckForNonExistingTenant(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantIdByName')
            ->with(self::TENANT)
            ->willReturn(null);

        $this->actingAsCustomer($this->customer)->post($this->generateRoute('storefront.m365.tenant-check'), [
            'tenantName' => self::TENANT,
        ])->assertNotFound()->assertExactJson(['message' => 'microsoft365.validation.tenant-not-found', 'errors' => []]);
    }

    #[Test]
    public function tenantCheckForNotAuthorizedTenant(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantIdByName')
            ->with(self::TENANT)
            ->willReturn(self::TENANT_ID);

        $this->microsoft365Service
            ->expects(self::once())
            ->method('hasDomainOwnership')
            ->with(self::TENANT_ID)
            ->willReturn(false);

        $this->actingAsCustomer($this->customer)->post($this->generateRoute('storefront.m365.tenant-check'), [
            'tenantName' => self::TENANT,
        ])->assertUnprocessable()->assertExactJson(['message' => 'microsoft365.validation.tenant-not-authorized', 'errors' => []]);
    }

    #[Test]
    public function tenantCheckForAuthorizedTenant(): void
    {
        $domain = 'yourhosting.nl';

        $customer = new CustomerFactory()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain($domain)
            ->createOne();

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain($domain)
            ->parentSubscription($domainSubscription)
            ->createOne();

        new DnsDeploymentFactory()->for($dnsSubscription)->createOne();

        $this->microsoft365Service
            ->expects(self::once())
            ->method('getTenantIdByName')
            ->with(self::TENANT)
            ->willReturn(self::TENANT_ID);

        $this->microsoft365Service
            ->expects(self::once())
            ->method('hasDomainOwnership')
            ->with(self::TENANT_ID)
            ->willReturn(true);

        $this->actingAsCustomer($this->customer)->post($this->generateRoute('storefront.m365.tenant-check'), [
            'tenantName' => self::TENANT,
        ])->assertOk()->assertExactJson(['tenantId' => self::TENANT_ID]);
    }

    #[Test]
    public function tenantCheckForInvalidTenantNames(): void
    {
        $this->actingAsCustomer($this->customer)->post($this->generateRoute('storefront.m365.tenant-check'), [
            'tenantName' => 'invalid-tenant-name',
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'Dit veld mag alleen letters en nummers bevatten.']);
    }
}
