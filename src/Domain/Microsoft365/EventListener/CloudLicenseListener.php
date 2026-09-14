<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Support\Facades\Log;
use SandwaveIo\Office365\Entity\CloudLicense;
use SandwaveIo\Office365\Library\Observer\CloudLicense\CloudLicenseObserverInterface;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class CloudLicenseListener implements CloudLicenseObserverInterface
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
    ) {
    }

    public function execute(CloudLicense $customer, ?Status $statusCode): void
    {
        $orderStatus = strtolower($statusCode?->getStatusCode() ?? '');
        $orderId = $customer->getOrderId();

        if ($orderId === null) {
            Log::error(sprintf(
                "%s::execute => KPN didn't provide an orderId. This is undocumented behaviour and shouldn't happen.",
                self::class,
            ));

            return;
        }

        if ($this->isModified($customer)) {
            $this->orderModified($customer);
        } else {
            match ($orderStatus) {
                // V4 of NewCloudLicenseOrderResponse no longer includes a CloudTenant block.
                // The tenant name is already persisted on the customer info via the TenantCreate flow.
                Microsoft365OrderStatus::ACCEPTED->value => $this->orderAccepted(
                    orderId: $orderId,
                    partnerReference: $customer->getPartnerReferenceHeader()?->getPartnerReference(),
                ),
                Microsoft365OrderStatus::ACTIVE->value => $this->orderActive(
                    orderId: $orderId,
                    quantity: $customer->getQuantity(),
                    dateCreated: $customer->getPartnerReferenceHeader()?->getDateCreated(),
                ),
                default => Log::error(sprintf(
                    "%s::execute => KPN provided an undocumented order status code of '%s'.",
                    self::class,
                    $orderStatus,
                )),
            };
        }
    }

    private function isModified(CloudLicense $cloudLicense): bool
    {
        $microsoft365Deployment = Microsoft365Deployment::where('kpn_order_id', $cloudLicense->getOrderId())->first();

        return $microsoft365Deployment?->kpn_status === Microsoft365OrderStatus::MODIFIED;
    }

    private function orderModified(CloudLicense $cloudLicense): void
    {
        $microsoft365Deployment = Microsoft365Deployment::where(
            'kpn_order_id',
            $cloudLicense->getOrderId(),
        )->firstOrFail();

        if ($microsoft365Deployment->kpn_status === Microsoft365OrderStatus::MODIFIED) {
            $newSeatCount = $cloudLicense->getQuantity();
            $oldInUseSeatCount = $microsoft365Deployment
                ->subscription
                ->children
                ->where('technical_status', TechnicalStatus::OK->value)
                ->whereNotIn('administrative_status', [
                    ...AdministrativeStatus::administrativelyEnded(),
                    AdministrativeStatus::ARCHIVING->value,
                ])
                ->count();

            if ($newSeatCount > $oldInUseSeatCount) {
                $this->activateNewlyboughtSeats($microsoft365Deployment, $newSeatCount - $oldInUseSeatCount);
            } elseif ($newSeatCount === $oldInUseSeatCount) {
                Log::error(
                    sprintf(
                        "%s::orderModified => KPN modify order that resulted in the KPN order with id '%d' has the same amount of seats that are already active (no change).",
                        self::class,
                        $cloudLicense->getOrderId(),
                    ),
                );

                return;
            }

            /**
             * When there is a negative delta for the amount of seats we don't need to do anything.
             * The subscriptions for those seats already have a canceled administrative status and will be removed with
             * KPN once the subscription expires.
             */
            $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::ACTIVE;
            $microsoft365Deployment->save();
        }
    }

    /** KPN accepted the order. They are now creating the order with Microsoft. The order is not yet active. */
    private function orderAccepted(int $orderId, ?string $partnerReference): void
    {
        if ($partnerReference === null || $partnerReference === '') {
            Log::error(
                sprintf(
                    "%s::orderAccepted => KPN didn't return the partner reference for order with ID %d. Unable to match with Microsoft365 subscription. This is undocumented behaviour and shouldn't happen.",
                    self::class,
                    $orderId,
                ),
            );

            return;
        }

        $matches = null;
        if (preg_match('/WF-ORDER-\d+-(\d+)/', $partnerReference, $matches) !== 1) {
            Log::error(
                sprintf(
                    '%s::orderAccepted => KPN returned a partner reference for order with ID %d with an unknown format: %s',
                    self::class,
                    $orderId,
                    $partnerReference,
                ),
            );

            return;
        }

        $microsoft365DeploymentId = $matches[1];
        $microsoft365Deployment = Microsoft365Deployment::where('id', $microsoft365DeploymentId)->first();

        if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
            Log::error(sprintf(
                '%s::orderAccepted => Unable to find %s with ID %s.',
                self::class,
                Microsoft365Deployment::class,
                $partnerReference,
            ));

            return;
        }

        $microsoft365Deployment->kpn_order_id = $orderId;
        $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::ACCEPTED;
        $microsoft365Deployment->save();
    }

    /** KPN marked the order as active. This means the order process is finalized on their end. */
    private function orderActive(int $orderId, int $quantity, ?DateTime $dateCreated): void
    {
        $microsoft365Deployment = Microsoft365Deployment::where('kpn_order_id', $orderId)->first();

        if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
            Log::error(sprintf(
                '%s::orderActive => Unable to find %s with ID %s.',
                self::class,
                Microsoft365Deployment::class,
                $orderId,
            ));

            return;
        }

        // The dateCreated is always set for NewCloudLicenseOrderResponse active responses. Cause it handles multiple statuses in the package it's nullable.
        assert($dateCreated !== null);

        $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::ACTIVE;
        $microsoft365Deployment->kpn_start_date = CarbonImmutable::instance($dateCreated);
        $microsoft365Deployment->save();

        $parentSubscription = $microsoft365Deployment->subscription;
        $seatSubscriptions = $parentSubscription
            ->children
            ->where('technical_status', TechnicalStatus::REGISTRATION->value)
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->take($quantity);

        if ($seatSubscriptions->count() !== $quantity) {
            $parentSubscription->technical_status = TechnicalStatus::ERROR->value;
            $parentSubscription->save();

            Log::error(
                sprintf(
                    '%s::orderActive => ordered %d seats but only %d child subscriptions available for new order with parent subscription id %d.',
                    self::class,
                    $quantity,
                    $seatSubscriptions->count(),
                    $parentSubscription->id,
                ),
            );

            return;
        }

        $parentSubscription->technical_status = TechnicalStatus::OK->value;
        $parentSubscription->save();

        $seatSubscriptions->each(
            function (Subscription $seatSubscription): void {
                $seatSubscription->technical_status = TechnicalStatus::OK->value;
                $seatSubscription->save();
            },
        );

        // The order process has succeeded at least once. This means that for this customer everything is set up correctly.
        // There is a KPN customer entity, a tenant, a customer agreement contact and a completed order.
        $microsoft365Deployment->microsoft365CustomerInfo->technical_status = Microsoft365ProcessStatus::ACTIVE;
        $microsoft365Deployment->microsoft365CustomerInfo->save();

        $this->microsoft365Service->retryPendingCopilotOrder($microsoft365Deployment->microsoft365CustomerInfo);
    }

    private function activateNewlyboughtSeats(Microsoft365Deployment $microsoft365Deployment, int $extraSeats): void
    {
        $children = $microsoft365Deployment
            ->subscription
            ->children
            ->where('technical_status', TechnicalStatus::REGISTRATION->value)
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::ARCHIVING->value,
            ]);

        $children
            ->take($extraSeats)
            ->each(
                function (Subscription $seatSubscription): void {
                    $seatSubscription->technical_status = TechnicalStatus::OK->value;
                    $seatSubscription->save();
                },
            );
    }
}
