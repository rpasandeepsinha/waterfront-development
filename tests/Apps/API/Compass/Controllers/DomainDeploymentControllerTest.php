<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DomainDeploymentController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainDeploymentController::class)]
class DomainDeploymentControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showDomainDeployment(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne(['domain' => self::DOMAIN]);

        $provider = new ProviderFactory()->domainRtr()->createOne();
        $businessUnit = new DomainProviderBusinessUnitFactory()->createOne(['name' => 'Yourhosting', 'slug' => 'yourhosting']);
        $lastResult = '{"domainName":"example.nl"}';

        new DomainDeploymentFactory()
            ->for($provider)
            ->for($businessUnit, 'businessUnit')
            ->withRtrDomainStatus(DomainStatus::PENDING_VALIDATION)
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
                'last_result' => $lastResult,
            ]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.deployment', ['domain' => self::DOMAIN]))
            ->assertOk()
            ->assertExactJson([
                'domain_status' => DomainStatus::PENDING_VALIDATION->value,
                'registry' => ProviderSlug::REALTIME_REGISTER->value,
                'business_unit' => 'Yourhosting',
                'business_unit_slug' => 'yourhosting',
                'last_result' => $lastResult,
            ]);
    }

    #[Test]
    public function showDomainDeploymentReturnsNotFoundWhenDeploymentIsMissing(): void
    {
        $translator = self::resolve(TranslatorInterface::class);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne(['domain' => self::DOMAIN]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.deployment', ['domain' => self::DOMAIN]))
            ->assertNotFound()
            ->assertExactJson([
                'message' => $translator->translate('domain-deployment.validation.not-found'),
            ]);
    }

    #[Test]
    public function showDomainDeploymentReturnsNotFoundWhenSubscriptionIsMissing(): void
    {
        $translator = self::resolve(TranslatorInterface::class);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.deployment', ['domain' => self::DOMAIN]))
            ->assertNotFound()
            ->assertExactJson([
                'message' => $translator->translate('domain-deployment.validation.not-found'),
            ]);
    }
}
