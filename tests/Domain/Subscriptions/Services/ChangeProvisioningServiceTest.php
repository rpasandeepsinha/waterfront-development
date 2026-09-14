<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Actions\ChangeBackupAction;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Redirects\Actions\UpgradeRedirectToHostingAction;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Services\ChangeProvisioningService;

#[CoversClass(ChangeProvisioningService::class)]
class ChangeProvisioningServiceTest extends IntegrationTestCase
{
    #[Test]
    public function receivedSubscriptionWithWrongGroupThrowsException(): void
    {
        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $domainProduct->uuid,
        ]);
        $randomProduct = new ProductFactory()->for($domainProduct->productGroup)->createOne();

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            self::resolve(ChangeHostingAction::class),
            self::resolve(ChangeDnsAction::class),
            self::resolve(ChangeBackupAction::class),
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($domainProduct->uuid),
            'to_product_uuid' => Uuid::fromString($randomProduct->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $this->expectException(Exception::class);
        $service->handleProvisioning($change);
    }

    #[Test]
    public function changeHostingActionGetsCalled(): void
    {
        $hostingAction = self::createMock(ChangeHostingAction::class);
        $hostingAction->expects(self::once())->method('execute');

        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $hostingProduct->uuid,
        ]);
        $hostingGrootProduct = new ProductFactory()->for($hostingProduct->productGroup)->createOne([
            'slug' => 'groot',
        ]);

        new HostingDeploymentFactory()->for($subscription)->createOne();

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            $hostingAction,
            self::resolve(ChangeDnsAction::class),
            self::resolve(ChangeBackupAction::class),
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($hostingProduct->uuid),
            'to_product_uuid' => Uuid::fromString($hostingGrootProduct->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $service->handleProvisioning($change);
    }

    #[Test]
    public function changeHostingActionDoesntGetCalledBecauseMissingDeployment(): void
    {
        $hostingAction = self::createMock(ChangeHostingAction::class);
        $hostingAction->expects(self::never())->method('execute');

        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $hostingProduct->uuid,
        ]);
        $hostingGrootProduct = new ProductFactory()->for($hostingProduct->productGroup)->createOne([
            'slug' => 'groot',
        ]);

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            $hostingAction,
            self::resolve(ChangeDnsAction::class),
            self::resolve(ChangeBackupAction::class),
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($hostingProduct->uuid),
            'to_product_uuid' => Uuid::fromString($hostingGrootProduct->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $this->expectException(Exception::class);
        $service->handleProvisioning($change);
    }

    #[Test]
    public function DnsChangeCallsChangeAction(): void
    {
        $dnsAction = self::createMock(ChangeDnsAction::class);
        $dnsAction->expects(self::once())->method('execute');

        $freeDns = new ProductFactory()->freeDns()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $freeDns->uuid,
        ]);
        $premiumDNS = new ProductFactory()->for($freeDns->productGroup)->createOne([
            'slug' => 'premiumDNS',
        ]);

        new DnsDeploymentFactory()
            ->withInternalNameserver()
            ->for($subscription)
            ->createOne();

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            self::resolve(ChangeHostingAction::class),
            $dnsAction,
            self::resolve(ChangeBackupAction::class),
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($freeDns->uuid),
            'to_product_uuid' => Uuid::fromString($premiumDNS->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $service->handleProvisioning($change);
    }

    #[Test]
    public function DnsChangeNoDeploymentFound(): void
    {
        $dnsAction = self::createMock(ChangeDnsAction::class);
        $dnsAction->expects(self::never())->method('execute');

        $freeDns = new ProductFactory()->freeDns()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $freeDns->uuid,
        ]);
        $premiumDNS = new ProductFactory()->for($freeDns->productGroup)->createOne();

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            self::resolve(ChangeHostingAction::class),
            $dnsAction,
            self::resolve(ChangeBackupAction::class),
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($freeDns->uuid),
            'to_product_uuid' => Uuid::fromString($premiumDNS->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $this->expectException(DnsDeploymentNotFoundException::class);
        $service->handleProvisioning($change);
    }

    #[Test]
    public function RedirectCallsUpgradeRedirectToHosting(): void
    {
        $redirectAction = self::createMock(UpgradeRedirectToHostingAction::class);
        $redirectAction->expects(self::once())->method('execute');

        $redirect = new ProductFactory()->freeRedirect()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $redirect->uuid,
        ]);
        $hosting = new ProductFactory()->for($redirect->productGroup)->createOne();

        new HostingDeploymentFactory()->for($subscription)->createOne();

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            self::resolve(ChangeHostingAction::class),
            self::resolve(ChangeDnsAction::class),
            self::resolve(ChangeBackupAction::class),
            $redirectAction,
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($redirect->uuid),
            'to_product_uuid' => Uuid::fromString($hosting->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $service->handleProvisioning($change);
    }

    #[Test]
    public function changeBackupActionsGetsCalled(): void
    {
        $backupAction = self::createMock(ChangeBackupAction::class);
        $backupAction->expects(self::once())->method('execute');

        $backupProduct = new ProductFactory()->backupAcronis()->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $backupProduct->uuid,
        ]);
        $backup250 = new ProductFactory()->for($backupProduct->productGroup)->createOne([
            'slug' => 'backup-250',
        ]);

        $service = new ChangeProvisioningService(
            self::resolve(ProductRepository::class),
            self::resolve(ChangeHostingAction::class),
            self::resolve(ChangeDnsAction::class),
            $backupAction,
            self::resolve(UpgradeRedirectToHostingAction::class),
            self::resolve(LoggerInterface::class),
        );

        $change = new SubscriptionChangeFactory()->createOne([
            'from_product_uuid' => Uuid::fromString($backupProduct->uuid),
            'to_product_uuid' => Uuid::fromString($backup250->uuid),
            'subscription_uuid' => $subscription->uuid,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $service->handleProvisioning($change);
    }
}
