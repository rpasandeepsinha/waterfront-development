<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Backup\Actions\ChangeBackupAction;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Redirects\Actions\UpgradeRedirectToHostingAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Support\Enums\LoggingContextKeys;

class ChangeProvisioningService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ChangeHostingAction $changeHostingAction,
        private readonly ChangeDnsAction $changeDnsAction,
        private readonly ChangeBackupAction $changeBackupAction,
        private readonly UpgradeRedirectToHostingAction $upgradeRedirectToHostingAction,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handleProvisioning(SubscriptionChange $changeToBeExecuted): void
    {
        $newProduct = $this->productRepository->findProductByUuid($changeToBeExecuted->to_product_uuid->toString());
        $oldProduct = $this->productRepository->findProductByUuid($changeToBeExecuted->from_product_uuid->toString());
        $subscription = $changeToBeExecuted->subscription;

        $this->logger->info(
            'Changing provisioning for: {subscription.uuid}',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::META => [
                    'subscription_change_id' => $changeToBeExecuted->id,
                    'from_product_slug' => $oldProduct->slug,
                    'to_product_slug' => $newProduct->slug,
                ],
            ]
        );

        $oldProduct->loadMissing('productGroup');
        match ($oldProduct->productGroup->slug) {
            ProductGroupType::HOSTING => $this->handleHosting($subscription, $oldProduct),
            ProductGroupType::DNS => $this->handleDNS($subscription, $changeToBeExecuted),
            ProductGroupType::REDIRECT => $this->handleRedirect($subscription, $newProduct),
            ProductGroupType::BACKUP => $this->handleBackup($subscription, $changeToBeExecuted),
            ProductGroupType::OTHER,
            ProductGroupType::SSL,
            ProductGroupType::RESELLER_HOSTING,
            ProductGroupType::VPS,
            ProductGroupType::MANUAL_SUBSCRIPTION,
            ProductGroupType::DOMAIN_EXPANSION,
            ProductGroupType::RESELLER_DISCOUNT,
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
            ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
            ProductGroupType::CLOUDSTACK_VOLUME,
            ProductGroupType::CLOUDSTACK_OS,
            ProductGroupType::MICROSOFT_365,
            ProductGroupType::ONE_TIME_SERVICE,
            ProductGroupType::VOLUME_DISCOUNT,
            ProductGroupType::EXTENSION,
            ProductGroupType::ADD_ON => throw new Exception("Found change for product and group that we don't support yet"),
        };
    }

    private function handleHosting(Subscription $subscription, Product $oldProduct): void
    {
        if (is_null($subscription->hostingDeployment)) {
            throw new Exception('No deployment');
        }

        $this->changeHostingAction->execute($subscription, $subscription->hostingDeployment, $oldProduct, $subscription->product);
    }

    private function handleRedirect(Subscription $subscription, Product $newProduct): void
    {
        $this->upgradeRedirectToHostingAction->execute($subscription, $newProduct);
    }

    private function handleDNS(Subscription $subscription, SubscriptionChange $change): void
    {
        if (is_null($subscription->dnsDeployment)) {
            throw new DnsDeploymentNotFoundException($subscription->domain ?? '');
        }

        $this->changeDnsAction->execute($subscription, $change->type);
    }

    private function handleBackup(Subscription $subscription, SubscriptionChange $change): void
    {
        $this->changeBackupAction->execute($subscription, $change);
    }
}
