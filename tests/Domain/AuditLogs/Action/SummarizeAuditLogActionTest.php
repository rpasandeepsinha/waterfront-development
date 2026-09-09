<?php

declare(strict_types=1);

namespace Tests\Domain\AuditLogs\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Tests\Factories\AuditFactory;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\SummarizeAuditLogAction;
use Waterfront\Domain\AuditLogs\DTO\AuditLoggableIdentity;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(SummarizeAuditLogAction::class)]
class SummarizeAuditLogActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private AuditLoggableIdentity $auditLoggableIdentity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne([
            'first_name' => 'Henk-wil',
            'last_name' => 'Veelbier',
        ]);

        $this->setUpTranslations();

        $this->auditLoggableIdentity = new AuditLoggableIdentity(Uuid::uuid4(), 'test@test.nl', SchemaId::EMPLOYEE->value);
    }

    #[Test]
    public function summarizeSubscriptionAuditLog(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()->for($product)->for($this->customer)->createOne([
            'domain' => 'really-long-useless-summary-audit-log-domain.extension',
        ]);

        new AuditFactory()->createMany([
            [
                'event' => 'created',
                'auditable_type' => Subscription::class,
                'auditable_id' => $this->subscription->id,
                'old_values' => '[]',
                'new_values' => ['technical_status' => TechnicalStatus::PENDING->value],
                'identity_uuid' => $this->customer->uuid,
            ],
            [
                'event' => 'updated',
                'auditable_type' => Subscription::class,
                'auditable_id' => $this->subscription->id,
                'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
                'new_values' => ['technical_status' => TechnicalStatus::OK->value],
                'identity_uuid' => $this->customer->uuid,
            ],
        ]);

        $summarizer = new SummarizeAuditLogAction(self::resolve(TranslatorInterface::class));

        $lastAudit = Audit::orderBy('id', 'desc')->firstOrFail();

        $this->subscription->with('product.productGroup')->firstOrFail();

        $summarizedLog = $summarizer->execute($lastAudit, $this->auditLoggableIdentity);

        self::assertSame(
            'Support bijgewerkt domein abonnement really-long-useless-summary-audit-log-domain.extension',
            $summarizedLog
        );
    }

    #[Test]
    public function summarizeCustomerAuditLog(): void
    {
        new AuditFactory()->create([
            'event' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
            'old_values' => '[]',
            'new_values' => ['first_name' => $this->customer->first_name],
            'identity_uuid' => $this->customer->uuid,
        ]);
        $summarizer = new SummarizeAuditLogAction(self::resolve(TranslatorInterface::class));

        $lastAudit = Audit::where('auditable_type', Customer::class)
            ->orderBy('id', 'desc')
            ->firstOrFail();

        $summarizedLog = $summarizer->execute($lastAudit, $this->auditLoggableIdentity);

        self::assertSame(
            'Support aangemaakt klant Henk-wil Veelbier',
            $summarizedLog
        );
    }

    #[Test]
    public function summarizeMicrosoft365(): void
    {
        $customer = new CustomerFactory()->createOne();
        $group = new ProductGroupFactory()->createOne([
            'name'           => 'Microsoft 365',
            'slug'           => ProductGroupType::MICROSOFT_365,
        ]);

        $product = new ProductFactory()->for($group)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $mainSubscription = new SubscriptionFactory()->for($product)->makeOne([
            'customer_id' => $customer->id,
            'domain' => null,
        ]);

        $product->subscriptions()->save($mainSubscription);

        $customerInfo = new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $customer->id,
            'tenant_name' => $customer->customer_number . '.onmicrosoft.com',
        ]);

        $microsoftSub = new Microsoft365DeploymentFactory()->createOne([
            'subscription_id' => $mainSubscription->id,
            'microsoft365_customer_info_id' => $customerInfo->id,
        ]);

        $audit = new AuditFactory()->createOne([
            'event' => 'updated',
            'auditable_type' => Microsoft365Deployment::class,
            'auditable_id' => $microsoftSub->id,
            'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
            'new_values' => ['technical_status' => TechnicalStatus::OK->value],
            'identity_uuid' => $this->customer->uuid,
        ]);

        $summarizer = new SummarizeAuditLogAction(self::resolve(TranslatorInterface::class));

        $summarizedLog = $summarizer->execute($audit, $this->auditLoggableIdentity);

        self::assertSame(
            'Support bijgewerkt Microsoft365 abonnement 2.onmicrosoft.com',
            $summarizedLog
        );
    }

    #[Test]
    public function summarizeCloudstackAuditLog(): void
    {
        $customer = new CustomerFactory()->createOne([
            'organization' => 'cloudstack',
        ]);

        $vpsProduct = ProductFactory::new()->vps()->createOne();

        $vpsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for($vpsProduct)
            ->createOne();

        $cloudstackVpsDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for(
                CloudstackManagerDomainDeploymentFactory::new()
                    ->for(CloudstackEnvironmentFactory::new())
                    ->for($customer)
                    ->state(['domain_name' => 'cs84583951', 'account' => 'cs84583951', 'username' => 'cs84583951'])
            )
            ->createOne([
                'subscription_uuid' => $vpsSubscription->uuid,
                'cloudstack_id' => 'f9d34f14-e9ec-4760-9da3-742d54864af9',
                'custom_name' => 'initial-name',
            ]);

        $audit = new AuditFactory()->createOne([
            'event' => 'updated',
            'auditable_type' => VirtualMachineDeployment::class,
            'auditable_id' => $cloudstackVpsDeployment->id,
            'old_values' => ['custom_name' => 'initial-name'],
            'new_values' => ['custom_name' => 'updated-name'],
            'identity_uuid' => $this->customer->uuid,
        ]);

        $summarizer = new SummarizeAuditLogAction(self::resolve(TranslatorInterface::class));

        $summarizedLog = $summarizer->execute($audit, $this->auditLoggableIdentity);

        self::assertSame(
            'Support bijgewerkt VPS abonnement cs84583951',
            $summarizedLog
        );
    }

    private function setUpTranslations(): void
    {
        $languageNL = new TranslationLanguageFactory()->createOne([
            'display_name' => 'Nederlands',
            'locale' => 'nl',
            'active' => false,
            'default' => false,
        ]);
        $languageEN = new TranslationLanguageFactory()->createOne([
            'display_name' => 'Engels',
            'locale' => 'en',
            'active' => true,
            'default' => true,
        ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, ':actor :event :entity :domain')
            ->withTranslatedString($languageEN, ':actor :event :entity :domain')
            ->create([
                'key' => 'audit-log-summary.subscription',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'bijgewerkt')
            ->withTranslatedString($languageEN, 'updated')
            ->create([
                'key' => 'audit-log-summary.event.updated',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'aangemaakt')
            ->withTranslatedString($languageEN, 'created')
            ->create([
                'key' => 'audit-log-summary.event.created',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'domein abonnement')
            ->withTranslatedString($languageEN, 'domain subscription')
            ->create([
                'key' => 'audit-log-summary.entity.subscription.extension',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'Microsoft365 abonnement')
            ->withTranslatedString($languageEN, 'Microsoft365 subscription')
            ->create([
                'key' => 'audit-log-summary.entity.subscription.microsoft-365',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'VPS abonnement')
            ->withTranslatedString($languageEN, 'VPS subscription')
            ->create([
                'key' => 'audit-log-summary.entity.subscription.vps',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, ':actor :event :entity :name')
            ->withTranslatedString($languageEN, ':actor :event :entity :name')
            ->create([
                'key' => 'audit-log-summary.customer',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'klant')
            ->withTranslatedString($languageEN, 'customer')
            ->create([
                'key' => 'audit-log-summary.entity.customer',
                'source' => 'audit-log-summary',
            ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'Support')
            ->withTranslatedString($languageEN, 'Support')
            ->create([
                'key' => 'audit-log-summary.actor.support',
                'source' => 'audit-log-summary',
            ]);
    }
}
