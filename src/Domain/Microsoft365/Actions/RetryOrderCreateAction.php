<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Actions;

use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Throwable;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365RetryOrderCreateResult;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class RetryOrderCreateAction
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly Microsoft365KpnProductRepository $microsoft365KpnProductRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Microsoft365Deployment $microsoft365Deployment): Microsoft365RetryOrderCreateResult
    {
        $customerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

        if ($customerInfo->tenant_order_id === null) {
            try {
                $tenantOrderIdSynchronized =
                    $this->microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($customerInfo);
            } catch (OrderSummaryCustomerNotFoundException|OrderSummaryException) {
                return Microsoft365RetryOrderCreateResult::ORDER_SUMMARY_RETRIEVAL_FAILED;
            }

            if (! $tenantOrderIdSynchronized) {
                try {
                    $tenantCreated =
                        $this->microsoft365Service->createTenant(
                            microsoft365CustomerInfo: $customerInfo,
                        );
                } catch (TenantNameTakenException|Office365Exception $exception) {
                    $this->logError($microsoft365Deployment, $exception);

                    return Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED;
                }

                if (! $tenantCreated) {
                    return Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED;
                }

                return Microsoft365RetryOrderCreateResult::TENANT_CREATED;
            }
        }

        if ($customerInfo->mca_signed_at === null) {
            return Microsoft365RetryOrderCreateResult::MCA_NOT_SIGNED;
        }

        if ($microsoft365Deployment->kpn_order_id === null) {
            Assert::notNull($customerInfo->kpn_customer_id);

            $kpnProduct = $this->microsoft365KpnProductRepository->getBySubscription($microsoft365Deployment->subscription);
            $childCount = $microsoft365Deployment
                ->subscriptionChildren
                ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                ->count();

            if ($childCount === 0) {
                return Microsoft365RetryOrderCreateResult::NO_SEATS;
            }

            try {
                $orderCreated = $this->microsoft365Service->createOrder(
                    microsoft365Deployment: $microsoft365Deployment,
                    productCode: $kpnProduct->kpn_product_code,
                    amount: $childCount,
                );
            } catch (Office365Exception $exception) {
                $this->logError($microsoft365Deployment, $exception);

                return Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED;
            }

            if (! $orderCreated) {
                return Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED;
            }
        }

        return Microsoft365RetryOrderCreateResult::ORDER_CREATED;
    }

    private function logError(Microsoft365Deployment $microsoft365Deployment, Throwable $exception): void
    {
        $customerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

        $this->logger->error(
            'Error while creating KPN order for customer {customer.id}: {domain.name}',
            [
                LoggingContextKeys::CUSTOMER_ID => $customerInfo->customer->id,
                LoggingContextKeys::DOMAIN_NAME => $customerInfo->tenant_name,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );
    }
}
