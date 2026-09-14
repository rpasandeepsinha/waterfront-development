<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use SandwaveIo\Office365\Response\OrderSummary;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

class Microsoft365SyncWatcherService
{
    private const int YEARLY_CONTRACT_PERIOD_MONTHS = 12;

    public function __construct(
        private readonly Microsoft365Repository $microsoft365Repository,
    ) {
    }

    /**
     * Get maximum 250 Microsoft 365 customers ordered on who last checked.
     *
     * @return Collection<int, Microsoft365CustomerInfo>
     */
    public function getMicrosoft365CustomersForWatching(): Collection
    {
        $week_ago = CarbonImmutable::now()->subWeek();

        return Microsoft365CustomerInfo::whereNotNull('kpn_customer_id')
            ->where('synced_at', '<', CarbonImmutable::parse($week_ago))
            ->orderBy('synced_at')
            ->limit(250)
            ->get();
    }

    public function handleMicrosoft365GhostSubscriptions(): void
    {
        $microsoft365GhostSubscriptions = $this->microsoft365Repository->findGhostMicrosoft365Subscriptions();

        if ($microsoft365GhostSubscriptions->count() > 0) {
            $this->createMicrosoft365SyncLog(
                sprintf(
                    'There are {%d} ghost subscriptions in the microsoft_365 group with the following id\'s: %s',
                    $microsoft365GhostSubscriptions->count(),
                    $microsoft365GhostSubscriptions->sort()->values()->toJson(),
                ),
            );
        }
    }

    public function handleMicrosoft365ParentAndChildSubscriptionsDeleted(): void
    {
        $microsoft365SubscriptionsToBeTerminated =
            $this->microsoft365Repository->findMicrosoft365ParentAndChildSubscriptionsDeleted();

        // Cant check for archiving/expired here as the subscription can still be active in IRMA
        foreach ($microsoft365SubscriptionsToBeTerminated as $microsoft365Deployment) {
            $waterfrontSeatCount = $microsoft365Deployment
                ->subscriptionChildren
                ->where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
                ->count();

            //Set Microsoft 365 subscription on terminated if subscription and children are all deleted
            if (
                $waterfrontSeatCount === 0
                && $microsoft365Deployment->subscription->administrative_status
                    === AdministrativeStatus::ARCHIVED->value
            ) {
                $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::TERMINATED;
                $microsoft365Deployment->save();
                $this->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 subscription {%d} has parent subscription and children which are all deleted. Changing kpn_status to {%s}.',
                        $microsoft365Deployment->id,
                        Microsoft365OrderStatus::TERMINATED->value,
                    ),
                    $microsoft365Deployment->microsoft365_customer_info_id,
                    $microsoft365Deployment->id,
                );
            }
        }
    }

    public function handleSeatProductMicrosoft365Subscriptions(): void
    {
        $childProductMicrosoft365Subscriptions =
            $this->microsoft365Repository->findSeatProductMicrosoft365Subscriptions();

        if ($childProductMicrosoft365Subscriptions->count() > 0) {
            $this->createMicrosoft365SyncLog(
                sprintf(
                    'There are {%d} subscriptions coupled to a seat product in the microsoft_365 group with the following id\'s: %s',
                    $childProductMicrosoft365Subscriptions->count(),
                    $childProductMicrosoft365Subscriptions->toJson(),
                ),
            );
        }
    }

    public function createMicrosoft365SyncLog(
        string $logMessage,
        ?int $microsoft365CustomerInfoId = null,
        ?int $microsoft365SubscriptionId = null,
    ): void {
        $log = new Microsoft365SyncLog();
        $log->log = $logMessage;
        $log->microsoft365_customer_info_id = $microsoft365CustomerInfoId;
        $log->microsoft365_deployment_id = $microsoft365SubscriptionId;
        $log->save();
    }

    /**
     * @param array<int, OrderSummary> $orderSummary
     */
    public function incorrectMicrosoft365CustomerStatus(
        array $orderSummary,
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ): void {
        if (
            count($orderSummary) > 0
            && $microsoft365CustomerInfo->technical_status !== Microsoft365ProcessStatus::ACTIVE
        ) {
            $this->createMicrosoft365SyncLog(
                sprintf(
                    'Microsoft365 customer {%d} had a {%s} state and now has a {%s} state despite having multiple subscriptions in irma.',
                    $microsoft365CustomerInfo->id,
                    Microsoft365OrderStatus::PLACED->value,
                    Microsoft365OrderStatus::ACTIVE->value,
                ),
                $microsoft365CustomerInfo->id,
            );

            $microsoft365CustomerInfo->technical_status = Microsoft365ProcessStatus::ACTIVE;
            $microsoft365CustomerInfo->save();
        }
    }

    public function checkWaterfrontIrmaAmountDifferences(
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
        int $irmaMicrosoft365SubscriptionCount,
    ): void {
        $waterfrontMicrosoft365SubscriptionCount = $microsoft365CustomerInfo::query()
            ->join(
                'microsoft365_deployments',
                'microsoft365_deployments.microsoft365_customer_info_id',
                '=',
                'microsoft365_customer_info.id',
            )
            ->join('subscriptions', 'subscriptions.id', '=', 'microsoft365_deployments.subscription_id')
            ->where('microsoft365_customer_info.id', '=', $microsoft365CustomerInfo->id)
            ->whereNot('subscriptions.administrative_status', AdministrativeStatus::ARCHIVED->value)
            // Yearly (NCE) subscriptions that are being cancelled (ARCHIVING / "Verwijderen") are already
            // moved out of IRMA's `Active` order list into `Terminate`, where they linger until the contract
            // period ends. When that happens IRMA sends a webhook that archives the subscription on our side.
            // Until that webhook arrives this would cause a false positive count mismatch, so skip them here.
            ->whereNot(
                fn (Builder $query) => $query->where(
                    'subscriptions.administrative_status',
                    AdministrativeStatus::ARCHIVING->value,
                )->where('subscriptions.contract_period', self::YEARLY_CONTRACT_PERIOD_MONTHS),
            )
            ->count();

        if ($waterfrontMicrosoft365SubscriptionCount === $irmaMicrosoft365SubscriptionCount) {
            return;
        }

        $this->createMicrosoft365SyncLog(
            sprintf(
                'There are {%d} subscriptions in Waterfront and {%d} subscriptions in Irma.',
                $waterfrontMicrosoft365SubscriptionCount,
                $irmaMicrosoft365SubscriptionCount,
            ),
            $microsoft365CustomerInfo->id,
        );
    }

    public function checkNoErrorsToday(): void
    {
        $syncLogExists = Microsoft365SyncLog::whereDate('created_at', '>=', CarbonImmutable::today())->exists();

        if (! $syncLogExists) {
            $this->createMicrosoft365SyncLog(
                sprintf(
                    'No problems found on %s.',
                    CarbonImmutable::today()->format(DateTimeFormat::DATE),
                ),
            );
        }
    }

    /**
     * @param OrderSummary[] $tenantOrderSummary
     */
    public function handleOrderSummary(
        array $tenantOrderSummary,
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ): void {
        foreach ($tenantOrderSummary as $order) {
            $orderId = $order->getOrderId();
            $kpnProductCode = $order->getProductId();

            $microsoft365Deployment = Microsoft365Deployment::where('kpn_order_id', $orderId)->first();

            if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
                $microsoft365Deployment = $this->findAndReplaceKpnOrderId(
                    $kpnProductCode,
                    $microsoft365CustomerInfo,
                    $orderId,
                );
                if ($microsoft365Deployment === null) {
                    continue;
                }
            }

            // Update the start date to the time it became active or when it was created
            if ($microsoft365Deployment->kpn_start_date !== $order->getDateActive()) {
                $microsoft365Deployment->kpn_start_date = $this->getKpnStartDate(
                    $order->getDateCreated(),
                    $order->getDateActive(),
                );
                $microsoft365Deployment->save();
            }

            // Update the KPN status to active if this status 'placed' or 'accepted'.
            if (in_array(
                $microsoft365Deployment->kpn_status,
                [Microsoft365OrderStatus::PLACED, Microsoft365OrderStatus::ACCEPTED],
                true,
            )) {
                $previousKpnStatus = $microsoft365Deployment->kpn_status;

                $this->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 subscription {%d} had a {%s} state and now has a {%s} state.',
                        $microsoft365Deployment->id,
                        $previousKpnStatus->value,
                        Microsoft365OrderStatus::ACTIVE->value,
                    ),
                    $microsoft365CustomerInfo->id,
                    $microsoft365Deployment->id,
                );

                $microsoft365Deployment->kpn_status = Microsoft365OrderStatus::ACTIVE;
                $microsoft365Deployment->save();
            }

            // Check if the KPN status is failed.
            if ($microsoft365Deployment->kpn_status === Microsoft365OrderStatus::FAILED) {
                $this->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 subscription {%d} has a failed state.',
                        $microsoft365Deployment->id,
                    ),
                    $microsoft365CustomerInfo->id,
                    $microsoft365Deployment->id,
                );
                continue;
            }

            // Check if the administrative and seats got the right administrative and technical status.
            $subscription = $microsoft365Deployment->subscription;
            $activeSeatCount = Subscription::where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
                ->where('parent_subscription_id', $subscription->id)
                ->count();
            if ($subscription->administrative_status === AdministrativeStatus::ARCHIVED->value) {
                if ($activeSeatCount > 0) {
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Subscription administrative {%d} has wrong administrative status {%s}. With {%d} seats not deleted.',
                            $microsoft365Deployment->id,
                            $microsoft365Deployment->subscription->administrative_status,
                            $activeSeatCount,
                        ),
                        $microsoft365CustomerInfo->id,
                        $microsoft365Deployment->id,
                    );
                    continue;
                }

                if (
                    $microsoft365Deployment->kpn_status === Microsoft365OrderStatus::TERMINATED
                    && $subscription->technical_status !== TechnicalStatus::DELETED->value
                ) {
                    $subscription->technical_status = TechnicalStatus::DELETED->value;
                    $subscription->save();
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Microsoft365 subscription {%d} has kpn_status terminated and administrative archived. Changing technical_status to {%s}.',
                            $microsoft365Deployment->id,
                            TechnicalStatus::DELETED->value,
                        ),
                        $microsoft365Deployment->microsoft365_customer_info_id,
                        $microsoft365Deployment->id,
                    );
                }
            }

            // Sets technical status to OK for subscriptions which are not archived / archiving.
            if ($subscription->technical_status !== TechnicalStatus::OK->value && $activeSeatCount > 0) {
                $this->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 subscription {%d} has subscription with technical status {%s} changed to {%s}.',
                        $microsoft365Deployment->id,
                        $subscription->technical_status,
                        TechnicalStatus::OK->value,
                    ),
                    $microsoft365CustomerInfo->id,
                    $microsoft365Deployment->id,
                );

                $subscription->technical_status = TechnicalStatus::OK->value;
                $subscription->save();
            }

            // Cause every seat is a subscription it runs out of memory with to many children therefore we have to chunk
            $this->chunkChildSubscriptions($subscription, $microsoft365Deployment, $microsoft365CustomerInfo);

            $irmaSeatCount = $order->getQuantity();

            // Cant check for archiving/expired here as the subscription can still be active in IRMA
            $waterfrontSeatCount = $microsoft365Deployment
                ->subscriptionChildren
                ->where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
                ->count();

            if ($irmaSeatCount > $waterfrontSeatCount || $irmaSeatCount < $waterfrontSeatCount) {
                $this->createMicrosoft365SyncLog(
                    sprintf(
                        'Found {%d} seats in Irma and {%d} seats in Waterfront with kpn_order_id {%d} for kpn_customer_id {%s}!',
                        $irmaSeatCount,
                        $waterfrontSeatCount,
                        $orderId,
                        $microsoft365CustomerInfo->kpn_customer_id,
                    ),
                    $microsoft365CustomerInfo->id,
                    $microsoft365Deployment->id,
                );
            }
        }
    }

    private function chunkChildSubscriptions(
        Subscription $subscription,
        Microsoft365Deployment $microsoft365Deployment,
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ): void {
        // Cant check for archiving/expired here as the subscription can still be active in IRMA
        Subscription::where(
            'parent_subscription_id',
            $subscription->id,
        )->chunk(100, function (Collection $children) use (
            $microsoft365Deployment,
            $microsoft365CustomerInfo,
            $subscription,
        ) {
            foreach ($children as $child) {
                if (
                    $child->technical_status !== TechnicalStatus::OK->value
                    && (
                        $child->administrative_status === AdministrativeStatus::ACTIVE->value
                        || $child->administrative_status === AdministrativeStatus::CANCELED->value
                    )
                ) {
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Microsoft365 subscription {%d} has seats with technical status {%s} changed to {%s}.',
                            $microsoft365Deployment->id,
                            $child->technical_status,
                            TechnicalStatus::OK->value,
                        ),
                        $microsoft365CustomerInfo->id,
                        $microsoft365Deployment->id,
                    );

                    $child->technical_status = TechnicalStatus::OK->value;
                    $child->save();
                }

                if (
                    $child->technical_status !== TechnicalStatus::DELETED->value
                    && $child->administrative_status === AdministrativeStatus::ARCHIVED->value
                ) {
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Microsoft365 subscription {%d} has seats with technical status {%s} changed to {%s}.',
                            $microsoft365Deployment->id,
                            $child->technical_status,
                            TechnicalStatus::DELETED->value,
                        ),
                        $microsoft365CustomerInfo->id,
                        $microsoft365Deployment->id,
                    );

                    $child->technical_status = TechnicalStatus::DELETED->value;
                    $child->save();
                }

                if (
                    $child->end_date->notEqualTo($subscription->end_date)
                    && $child->administrative_status === AdministrativeStatus::ACTIVE->value
                ) {
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Microsoft365 subscription {%d} has seats with end date {%s} changed to {%s}.',
                            $microsoft365Deployment->id,
                            $child->end_date,
                            $subscription->end_date,
                        ),
                        $microsoft365CustomerInfo->id,
                        $microsoft365Deployment->id,
                    );

                    $child->end_date = $subscription->end_date;
                    $child->save();
                }

                if (
                    $child->next_billing_date->notEqualTo($subscription->next_billing_date)
                    && $child->administrative_status === AdministrativeStatus::ACTIVE->value
                ) {
                    $this->createMicrosoft365SyncLog(
                        sprintf(
                            'Microsoft365 subscription {%d} has seats with next billing date {%s} changed to {%s}.',
                            $microsoft365Deployment->id,
                            $child->next_billing_date,
                            $subscription->next_billing_date,
                        ),
                        $microsoft365CustomerInfo->id,
                        $microsoft365Deployment->id,
                    );

                    $child->next_billing_date = $subscription->next_billing_date;
                    $child->save();
                }
            }
        });
    }

    private function findAndReplaceKpnOrderId(
        string $kpnProductCode,
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
        int $orderId,
    ): ?Microsoft365Deployment {
        $microsoft365Subscriptions = Microsoft365Deployment::query()
            ->select('microsoft365_deployments.*')
            ->join('subscriptions', 'subscriptions.id', '=', 'microsoft365_deployments.subscription_id')
            ->join('products', 'products.uuid', '=', 'subscriptions.product_uuid')
            ->join(
                'microsoft365_kpn_product',
                fn (JoinClause $join) => $join->on('microsoft365_kpn_product.product_id', '=', 'products.id')->on(
                    'microsoft365_kpn_product.contract_period',
                    '=',
                    'subscriptions.contract_period',
                ),
            )
            ->where('microsoft365_kpn_product.kpn_product_code', '=', $kpnProductCode)
            ->where('microsoft365_deployments.microsoft365_customer_info_id', '=', $microsoft365CustomerInfo->id)
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::ARCHIVING->value,
            ])
            ->get();

        if (count($microsoft365Subscriptions) !== 1) {
            $this->createMicrosoft365SyncLog(
                sprintf(
                    'Microsoft365 subscription with orderId {%d} found in Irma but not in Waterfront. No other subscription found with this product.',
                    $orderId,
                ),
                $microsoft365CustomerInfo->id,
            );

            return null;
        }

        $microsoft365Deployment = $microsoft365Subscriptions->first();
        assert($microsoft365Deployment instanceof Microsoft365Deployment);

        $this->createMicrosoft365SyncLog(
            sprintf(
                'Microsoft365 subscription was kpn_order_id {%s} and is now updated to {%d}. Updating subscription with the same product.',
                $microsoft365Deployment->kpn_order_id === null
                    ? 'null'
                    : (string) $microsoft365Deployment->kpn_order_id,
                $orderId,
            ),
            $microsoft365CustomerInfo->id,
            $microsoft365Deployment->id,
        );

        $microsoft365Deployment->kpn_order_id = $orderId;
        $microsoft365Deployment->save();

        return $microsoft365Deployment;
    }

    private function getKpnStartDate(DateTime $dateCreated, ?DateTime $dateActive): CarbonImmutable
    {
        return CarbonImmutable::instance($dateActive ?? $dateCreated);
    }
}
