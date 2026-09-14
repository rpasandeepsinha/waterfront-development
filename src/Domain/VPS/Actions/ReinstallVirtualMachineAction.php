<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;

class ReinstallVirtualMachineAction
{
    public function __construct(
        private readonly SubscriptionChangeService $changeService,
        private readonly VirtualMachineServiceInterface $vmService,
        private readonly ProductRepository $productRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    public function execute(
        Subscription $subscription,
        VirtualMachineDeployment $deployment,
        string $newOsUuid,
        ?string $sshKeyUuid = null,
    ): bool {
        try {
            $newProduct = $this->productRepository->findProductByUuid($newOsUuid);
            $this->changeService->change(
                ProductChangeType::REINSTALL,
                $subscription,
                $newProduct,
                invoiceTheChange: false,
                sendMail: false,
            );
        } catch (SubscriptionChangeException $exception) {
            $this->logger->error(
                'Failed to change product for subscription during VM reinstall',
                [
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PRODUCT_UUID => $newOsUuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
            throw new InvalidArgumentException(
                'administrative change failed when reinstalling the VM',
                $exception->getCode(),
                $exception,
            );
        }

        return $this->vmService->reinstall($deployment, $newProduct, $sshKeyUuid);
    }
}
