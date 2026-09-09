<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Office365\Entity\Terminate;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use SandwaveIo\Office365\Library\Observer\Terminate\TerminateObserverInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class TerminateOrderListener implements TerminateObserverInterface
{
    public function execute(Terminate $terminate, ?Status $status): void
    {
        $kpnOrderId = str_replace('OID', '', $terminate->getOrderId());

        // Status 205 (Terminate accepted) can be ignored
        if ($status !== null && $status->getStatusCode() === '205') {
            return;
        }

        $microsoft365Deployment = Microsoft365Deployment::where('kpn_order_id', intval($kpnOrderId))->first();

        // If an order is modified it will not find the original order id because the original record is updated with an upgrade order id
        // It might also be possible that the order is simply unknown in waterfront, meaning irma and waterfront are out of sync
        if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
            Log::error(
                sprintf(
                    "%s::execute - KPN order id '%d' could not be found.",
                    self::class,
                    $terminate->getOrderId(),
                )
            );
            return;
        }

        Log::info(
            sprintf(
                "%s::execute -> received termination webhook call for order_id '%d'",
                self::class,
                $terminate->getOrderId(),
            )
        );

        $subscription = $microsoft365Deployment->subscription;

        if ($subscription->administrative_status === AdministrativeStatus::ARCHIVING->value) {
            $subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $subscription->technical_status = TechnicalStatus::DELETED->value;
            $subscription->save();

            $allArchivingChildren = $subscription->children->filter(fn (Subscription $subscription) => $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value);
            foreach ($allArchivingChildren as $child) {
                $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
                $child->technical_status = TechnicalStatus::DELETED->value;
                $child->save();
            }

            if ($subscription->children->filter(fn (Subscription $subscription) => $subscription->administrative_status !== AdministrativeStatus::ARCHIVED->value)->count() === 0) {
                $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::TERMINATED;
                $microsoft365Deployment->save();
            }
        } else {
            Log::error(
                sprintf(
                    "%s::execute - Subscription '%d' with administrative status '%s' should not be in the terminate order listener.",
                    self::class,
                    $subscription->id,
                    $subscription->administrative_status,
                )
            );
        }
    }
}
