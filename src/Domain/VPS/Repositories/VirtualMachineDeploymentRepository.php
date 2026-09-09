<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

class VirtualMachineDeploymentRepository implements VirtualMachineDeploymentRepositoryInterface
{
    /**
     *
     * @throws VirtualMachineNotFoundException
     */
    public function findBySubscriptionUuid(string $uuid, int $customerId): VirtualMachineDeployment
    {
        try {
            return VirtualMachineDeployment::query()
                ->where('subscription_uuid', $uuid)
                ->whereHas('subscription.customer', function (Builder $query) use ($customerId): void {
                    $query->where('id', $customerId);
                })
                ->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            throw new VirtualMachineNotFoundException(
                sprintf('Virtual machine deployment "%s" not found.', $uuid),
                0,
                $exception
            );
        }
    }

    public function firstOrCreateBySubscriptionUuid(
        string $subscriptionUuid,
        ManagerDomainDeployment $managerDomainDeployment,
    ): VirtualMachineDeployment {
        $vmDeployment = VirtualMachineDeployment::withTrashed()->firstOrNew([
            'subscription_uuid' => $subscriptionUuid,
        ]);

        if ($vmDeployment->trashed()) {
            $vmDeployment->restore();

            $vmDeployment->cloudstack_id = null;
            $vmDeployment->last_result = null;
            $vmDeployment->last_action_status = null;
        }

        $vmDeployment->manager_domain_deployment_id = $managerDomainDeployment->id;

        if (! $vmDeployment->exists || $vmDeployment->isDirty()) {
            $vmDeployment->save();
        }

        return $vmDeployment;
    }

    /**
     * @throws VirtualMachineNotFoundException
     */
    public function getOsSubscriptionChildFromSubscriptionUuid(string $subscriptionUuid): Subscription
    {
        try {
            $parentSubscriptionWithChild = Subscription::query()
                ->where('uuid', $subscriptionUuid)
                ->whereHas('children.product.productGroup', function (Builder $query) {
                    $query->where('slug', ProductGroupType::CLOUDSTACK_OS);
                })
                ->with('children')
                ->firstOrFail();

            return $parentSubscriptionWithChild->children->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            throw new VirtualMachineNotFoundException(sprintf(
                'Could not find a subscription for VM %s with a Cloudstack OS as child subscription.',
                $subscriptionUuid
            ), $exception->getCode(), $exception);
        }
    }

    public function updateCustomName(VirtualMachineDeployment $virtualMachineDeployment, string $customName): bool
    {
        $virtualMachineDeployment->custom_name = $customName;
        return $virtualMachineDeployment->save();
    }
}
