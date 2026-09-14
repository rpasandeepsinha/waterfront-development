<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Office365\Entity\OrderModifyQuantity;
use SandwaveIo\Office365\Library\Observer\Order\OrderModifyQuantityObserverInterface;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ModifyOrderQuantityListener implements OrderModifyQuantityObserverInterface
{
    public function execute(OrderModifyQuantity $modifyOrderQuantity, ?Status $status): void
    {
        $microsoft365Deployment = Microsoft365Deployment::where(
            'kpn_order_id',
            $modifyOrderQuantity->getOrderId(),
        )->first();

        if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
            Log::error(sprintf(
                '%s::execute -> Subscription failed to fetch using KPN Order ID %s.',
                self::class,
                $modifyOrderQuantity->getOrderId(),
            ));

            return;
        }

        $statusCode = strtolower($status?->getStatusCode() ?? '');

        Log::info(
            sprintf(
                "%s::execute -> received KPN modify order webhook call for %s with id '%d' and KPN order number '%s'",
                self::class,
                Microsoft365Deployment::class,
                $microsoft365Deployment->id,
                $microsoft365Deployment->kpn_order_id,
            ),
        );

        switch ($statusCode) {
            case Microsoft365OrderStatus::MODIFY_PENDING->value:
                $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::MODIFY_PENDING;
                break;
            case Microsoft365OrderStatus::MODIFIED->value:
                $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::MODIFIED;
                $microsoft365Deployment->kpn_order_id = $modifyOrderQuantity->getUpgradeOrderId();

                $subscription = $microsoft365Deployment->subscription;
                $allArchivingChildren = $subscription->children->filter(
                    fn (Subscription $subscription) => (
                        $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                    ),
                );

                foreach ($allArchivingChildren as $child) {
                    $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
                    $child->save();
                }

                break;
            default:
                Log::error(sprintf(
                    "%s::execute -> received KPN modify order webhook call with undocumented status code '%s'",
                    self::class,
                    $statusCode,
                ));

                return;
        }

        $microsoft365Deployment->save();
    }
}
