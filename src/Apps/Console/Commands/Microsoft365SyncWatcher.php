<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SyncWatcherService;

#[AsCommand(name: 'microsoft365:sync-watcher')]
#[Description('Command to keep an eye on the sync between Irma and Waterfront.')]
class Microsoft365SyncWatcher extends AbstractCommand
{
    public function handle(
        Microsoft365Service $microsoft365Service,
        Microsoft365SyncWatcherService $microsoft365SyncWatcherService,
    ): int {
        // Detects and logs incorrect Microsoft 365 subscriptions
        $microsoft365SyncWatcherService->handleMicrosoft365GhostSubscriptions();
        $microsoft365SyncWatcherService->handleSeatProductMicrosoft365Subscriptions();
        $microsoft365SyncWatcherService->handleMicrosoft365ParentAndChildSubscriptionsDeleted();

        // Get 250 Microsoft 365 customers which have been the last to be synced
        $microsoft365CustomerInfos = $microsoft365SyncWatcherService->getMicrosoft365CustomersForWatching();
        $this->line('Watching ' . $microsoft365CustomerInfos->count() . ' Microsoft365 customers.');

        foreach ($microsoft365CustomerInfos as $microsoft365CustomerInfo) {
            // The query filters for kpn_customer_id not null, this will cause no issues.
            assert(is_string($microsoft365CustomerInfo->kpn_customer_id));

            if (! str_starts_with($microsoft365CustomerInfo->kpn_customer_id, 'CID')) {
                $microsoft365SyncWatcherService->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 customer {%d} has a invalid kpn_customer_id {%s}.',
                        $microsoft365CustomerInfo->id,
                        $microsoft365CustomerInfo->kpn_customer_id,
                    ),
                    $microsoft365CustomerInfo->id,
                );
                continue;
            }

            // Might be failed but might still have subscriptions therefore no continue.
            if ($microsoft365CustomerInfo->technical_status === Microsoft365ProcessStatus::FAILED) {
                $microsoft365SyncWatcherService->createMicrosoft365SyncLog(
                    sprintf(
                        'Microsoft365 customer {%d} has a failed state.',
                        $microsoft365CustomerInfo->id,
                    ),
                    $microsoft365CustomerInfo->id,
                );
            }

            $tenantOrderIdFromSummary = null;

            try {
                $tenantOrderSummary = $microsoft365Service->orderSummary(
                    customer: (int) str_replace('CID', '', $microsoft365CustomerInfo->kpn_customer_id),
                    orderState: 'Active',
                );
            } catch (OrderSummaryException $e) {
                if (stripos($e->getMessage(), 'Too Many Requests') !== false) {
                    $microsoft365SyncWatcherService->createMicrosoft365SyncLog(
                        'Microsoft365 sync watcher has reached it\'s too many request limit!',
                        $microsoft365CustomerInfo->id,
                    );
                    return self::FAILURE;
                }

                $microsoft365SyncWatcherService->createMicrosoft365SyncLog(
                    sprintf(
                        'Error while retrieving order summary for KPN customer with id {%s}. With exception message: %s',
                        $microsoft365CustomerInfo->kpn_customer_id,
                        $e->getMessage(),
                    ),
                    $microsoft365CustomerInfo->id,
                );
                continue;
            } catch (OrderSummaryCustomerNotFoundException) {
                $microsoft365SyncWatcherService->createMicrosoft365SyncLog(
                    sprintf(
                        'KPN Customer with id: {%s} not found.',
                        $microsoft365CustomerInfo->kpn_customer_id,
                    ),
                    $microsoft365CustomerInfo->id,
                );
                continue;
            }

            // 282A00001B aka Microsoft Tenant is an administrative product. Extract its order id
            // (so we can sync tenant_order_id on the customer info below) and remove it from the
            // list before the rest of the watcher processes it.
            foreach ($tenantOrderSummary as $key => $orderSummary) {
                if ($orderSummary->getProductId() === Microsoft365Service::MICROSOFT_TENANT_PRODUCT_CODE) {
                    // Sync tenant_order_id when it's still missing.
                    if ($microsoft365CustomerInfo->tenant_order_id === null) {
                        $microsoft365CustomerInfo->tenant_order_id = $orderSummary->getOrderId();
                        $microsoft365CustomerInfo->save();
                    }
                    unset($tenantOrderSummary[$key]);
                    break;
                }
            }

            // Check differences between Irma and Waterfront count
            $microsoft365SyncWatcherService->checkWaterfrontIrmaAmountDifferences($microsoft365CustomerInfo, count($tenantOrderSummary));

            // Some customers have active orders but wrong technical status
            $microsoft365SyncWatcherService->incorrectMicrosoft365CustomerStatus($tenantOrderSummary, $microsoft365CustomerInfo);

            // Handle order summary
            $microsoft365SyncWatcherService->handleOrderSummary($tenantOrderSummary, $microsoft365CustomerInfo);

            $microsoft365CustomerInfo->synced_at = CarbonImmutable::now();
            $microsoft365CustomerInfo->save();

            $this->line(
                sprintf(
                    'Microsoft365 customer {%d} has successfully been checked.',
                    $microsoft365CustomerInfo->id,
                ),
            );
        }

        // Check if today no error were found in the sync
        $microsoft365SyncWatcherService->checkNoErrorsToday();

        $this->line('Finished watching the Microsoft365 subscriptions.');

        return self::SUCCESS;
    }
}
