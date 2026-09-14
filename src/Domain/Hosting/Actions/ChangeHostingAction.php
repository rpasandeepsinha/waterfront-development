<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Hosting\Exceptions\HostingProviderNotFoundException;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class ChangeHostingAction
{
    public function __construct(
        private readonly HostingServiceFactory $hostingServiceFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws HostingProviderNotFoundException
     * @throws DriverNotDefinedException
     */
    public function execute(
        Subscription $subscription,
        HostingDeployment $hostingDeployment,
        Product $oldProduct,
        Product $newProduct,
        ?string $originalServicePlan = null,
    ): SubscriptionChangeResult {
        // Check if there is a server. If not and the current subscription is a mail only, try to resolve a new one.
        $this->resolveMailServerForMailUpgrades($hostingDeployment, $subscription, $oldProduct);

        if ($hostingDeployment->provider === null) {
            throw new HostingProviderNotFoundException(
                sprintf(
                    'No hosting provider found for subscription with UUID "%s".',
                    $subscription->uuid,
                ),
            );
        }

        $this->logger->info(
            'Changing service plan for subscription {subscription.uuid} to product id {product.id}',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::PRODUCT_ID => $newProduct->id,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                    $subscription->customer->migratedCustomers->first()?->reference_customer_number,
                LoggingContextKeys::META => [
                    'from_product_slug' => $oldProduct->slug,
                    'from_remote_service_plan' => $originalServicePlan,
                    'to_product_slug' => $newProduct->slug,
                ],
            ],
        );

        try {
            // Perform the technical up- or downgrade.
            $provisioningResult = $this->hostingServiceFactory->driver($hostingDeployment->provider->slug)->changeServicePlan(
                $hostingDeployment,
                $oldProduct,
                $newProduct,
            );

            $result = new SubscriptionChangeResult(
                status: (string) $provisioningResult->getStatus(),
                errorCode: $provisioningResult->getErrorCode(),
                errorMessage: $provisioningResult->getErrorMessage(),
            );

            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Technical downgrade or upgrade to product id {product.id} not performed for subscription with id : {subscription.id} (uuid : {subscription.uuid) due to lack of resources. ExceptionMessage : {error.message}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PRODUCT_ID => $newProduct->id,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $subscription->customer->migratedCustomers->first()?->reference_customer_number,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $oldProduct->slug,
                        'from_remote_service_plan' => $originalServicePlan,
                        'to_product_slug' => $newProduct->slug,
                    ],
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $result = new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: $exception->getCode(),
                errorMessage: $exception->getMessage(),
            );
        }

        return $result;
    }

    /**
     * @throws HostingProviderNotFoundException
     */
    private function resolveMailServerForMailUpgrades(
        HostingDeployment $hostingDeployment,
        Subscription $subscription,
        Product $oldProduct,
    ): void {
        if ($hostingDeployment->mailOnlyServer !== null && $oldProduct->isMailOnlyServer()) {
            $mailOnlyServer = $hostingDeployment->mailOnlyServer;

            $providerSlug = $mailOnlyServer->type === ServerType::PLESK
                ? ProviderSlug::PLESK->value
                : ProviderSlug::DIRECTADMIN->value;

            $hostingProvider = Provider::where('slug', $providerSlug)->where('type', ProviderType::HOSTING)->first();

            if (! $hostingProvider instanceof Provider) {
                throw new HostingProviderNotFoundException(
                    sprintf(
                        'No convertible hosting provider found with the slug "%s" for subscription with UUID "%s".',
                        $providerSlug,
                        $subscription->uuid,
                    ),
                );
            }

            $hostingDeployment->update([
                'server_id' => $mailOnlyServer->id,
                'provider_id' => $hostingProvider->id,
                'mail_only_provider_id' => null,
                'mail_only_server_id' => null,
            ]);
        }
    }
}
