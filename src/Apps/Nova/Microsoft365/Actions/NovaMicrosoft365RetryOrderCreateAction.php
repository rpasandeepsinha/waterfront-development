<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaMicrosoft365RetryOrderCreateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Microsoft365Service $microsoft365Service,
        private readonly Microsoft365KpnProductRepository $microsoft365KpnProductRepository,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry-microsoft365-order-create');
    }

    /**
     * @param Collection<int, Microsoft365Deployment> $microsoft365Deployments
     */
    public function handle(ActionFields $fields, Collection $microsoft365Deployments): ActionResponse|static
    {
        $customerInfo = $microsoft365Deployments->first()?->microsoft365CustomerInfo;
        Assert::notNull($customerInfo);

        if ($customerInfo->tenant_order_id === null) {
            try {
                $tenantOrderIdSynchronized = $this->microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($customerInfo);
            } catch (OrderSummaryCustomerNotFoundException|OrderSummaryException) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-summary-retrieval'));
            }

            if (! $tenantOrderIdSynchronized) {
                try {
                    $success = $this->microsoft365Service->createTenant(
                        microsoft365CustomerInfo: $customerInfo
                    );
                } catch (TenantNameTakenException|Office365Exception $exception) {
                    Log::error(
                        sprintf(
                            'Error while creating order for customer_id: [%s] with tenant name: [%s]. With exception message: %s',
                            $customerInfo->customer->id,
                            $customerInfo->tenant_name,
                            $exception->getMessage(),
                        ),
                        [
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                            LoggingContextKeys::CUSTOMER_ID => $customerInfo->customer->id,
                            LoggingContextKeys::EXCEPTION => $exception,
                        ]
                    );

                    return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-created'));
                }

                if (! $success) {
                    return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-created'));
                }

                return Action::message($this->translator->translate('nova-action.success.microsoft365-tenant-created'));
            }
        }

        /** @var Microsoft365Deployment $microsoft365Deployment */
        foreach ($microsoft365Deployments as $microsoft365Deployment) {
            $microsoft365CustomerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

            if ($microsoft365CustomerInfo->mca_signed_at === null) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-mca-not-signed'));
            }

            if ($microsoft365Deployment->kpn_order_id === null) {
                $kpnCustomerId = $microsoft365Deployment->microsoft365CustomerInfo->kpn_customer_id;
                Assert::notNull($kpnCustomerId);
                $customer = $microsoft365Deployment->microsoft365CustomerInfo->customer;
                $subscription = $microsoft365Deployment->subscription;

                $kpnProduct = $this->microsoft365KpnProductRepository->getBySubscription($subscription);
                $childCount = $microsoft365Deployment->subscriptionChildren
                    ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                    ->count();

                if ($childCount === 0) {
                    return self::danger($this->translator->translate('nova-action.failed.microsoft365-no-seats'));
                }

                try {
                    $success = $this->microsoft365Service->createOrder(
                        microsoft365Deployment: $microsoft365Deployment,
                        productCode: $kpnProduct->kpn_product_code,
                        amount: $childCount,
                    );
                } catch (Office365Exception $e) {
                    Log::error(
                        sprintf(
                            'Error while creating order for customer_id: [%s] with tenant name: [%s]. With exception message: %s',
                            $customer->id,
                            $microsoft365Deployment->microsoft365CustomerInfo->tenant_name,
                            $e->getMessage(),
                        ),
                        [
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                            LoggingContextKeys::CUSTOMER_ID => $customer->id,
                            LoggingContextKeys::EXCEPTION => $e,
                        ]
                    );

                    return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-created'));
                }

                if (! $success) {
                    return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-created'));
                }
            }
        }

        return Action::message($this->translator->translate('nova-action.success.microsoft365-order-created'));
    }
}
