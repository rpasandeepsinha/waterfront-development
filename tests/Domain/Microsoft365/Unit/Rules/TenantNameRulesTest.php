<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Rules\TenantNameRules;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(TenantNameRules::class)]
class TenantNameRulesTest extends IntegrationTestCase
{
    private readonly Customer $customerWithTenant;

    private readonly Customer $customerWithoutTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerWithTenant = new CustomerFactory()->createOne();
        $this->customerWithoutTenant = new CustomerFactory()->createOne();

        new Microsoft365CustomerInfoFactory()->for($this->customerWithTenant)->createOne();
    }

    #[Test]
    public function ruleForCustomerWithTenant(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithTenant,
        );

        $rule->validate('attribute', null, self::assertClosureIsCalled(false));
    }

    #[Test]
    public function ruleForCustomerWithTenantAndTenantGiven(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithTenant,
        );

        $rule->validate('attribute', 'sandwave', self::assertClosureIsCalled(true));
    }

    #[Test]
    public function tenantNameIsMissing(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithoutTenant,
        );

        $rule->validate('attribute', null, self::assertClosureIsCalled(true));
    }

    #[Test]
    public function tenantNameContainsNonAlphaNumericCharacters(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithoutTenant,
        );

        $rule->validate('attribute', 'invalid-tenant-name', self::assertClosureIsCalled(true));
        $rule->validate('attribute', 'invalid.tenant.name', self::assertClosureIsCalled(true));
        $rule->validate('attribute', 'invalid_tenant_name', self::assertClosureIsCalled(true));
        $rule->validate('attribute', 'invalid tenant name', self::assertClosureIsCalled(true));
    }

    #[Test]
    public function tenantNameToLong(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithoutTenant,
        );

        $longButValidTenantName = 'aVeryVeryVeryLongTenantName'; // exactly 27 chars
        $toLongTenantName = $longButValidTenantName . '1'; // 28 chars

        $rule->validate('attribute', $longButValidTenantName, self::assertClosureIsCalled(false));
        $rule->validate('attribute', $toLongTenantName, self::assertClosureIsCalled(true));
    }

    #[Test]
    public function validTenantName(): void
    {
        $rule = new TenantNameRules(
            translator: self::createStub(TranslatorInterface::class),
            customer: $this->customerWithoutTenant,
        );

        $rule->validate('attribute', 'aValidTenantName123', self::assertClosureIsCalled(false));
    }
}
