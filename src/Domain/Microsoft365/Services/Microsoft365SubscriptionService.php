<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class Microsoft365SubscriptionService
{
    private const string MICROSOFT_SUBDOMAIN = '.onmicrosoft.com';

    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly Dispatcher $dispatcher,
        private readonly Microsoft365TenantService $microsoft365TenantService,
        private readonly Microsoft365KpnProductRepository $microsoft365KpnProductRepository,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    /**
     * @param Collection<Subscription> $subscriptions
     */
    public function create(Collection $subscriptions, null|string $tenantName, null|string $tenantId): void
    {
        $firstSubscription = $subscriptions->firstOrFail();
        assert($firstSubscription instanceof Subscription);
        /** @var Customer $customer */
        $customer = $firstSubscription->customer;
        $isFirstTimeMicrosoftCustomer = $this->checkFirstTimeCustomer($customer, $tenantName);

        if ($isFirstTimeMicrosoftCustomer) {
            $this->initiateFirstTimeCustomer($customer, $tenantName, $tenantId, $firstSubscription);
        }

        if ($tenantName !== null) {
            $customerInfo = Microsoft365CustomerInfo::where('customer_id', $customer->id)->where('tenant_name', $tenantName)->firstOrFail();
        } else {
            $customerInfo = Microsoft365CustomerInfo::where('customer_id', $customer->id)->firstOrFail();
        }

        $subscriptions->groupBy('product_uuid')->each(
            function (Collection $seatSubscriptions) use ($customerInfo, $customer, $isFirstTimeMicrosoftCustomer): void {
                $firstSubscription = $seatSubscriptions->firstOrFail();
                assert($firstSubscription instanceof Subscription);
                $parentSubscription = $this->createParentSubscriptionIfNeeded($firstSubscription, $customer, $customerInfo, $isFirstTimeMicrosoftCustomer);

                /** The customer bought a new seat for a canceled parent subscription. This should be possible. */
                if ($parentSubscription->administrative_status === AdministrativeStatus::CANCELED->value) {
                    $parentSubscription->update([
                        'administrative_status' => AdministrativeStatus::ACTIVE->value,
                        'cancel_date' => null,
                    ]);
                }

                /** @var Collection<int, Subscription> $seatSubscriptions */
                $this->attachChildrenToParentSubscription($seatSubscriptions, $parentSubscription);

                if ($isFirstTimeMicrosoftCustomer) {
                    /**
                     * We can't continue since we need a customer entity at KPN. This is the normal flow for a new Microsoft 365
                     * customer. As soon as we get a message back from KPN that a customer is made the order will be
                     * submitted.
                     */
                    return;
                }

                $this->createOrModifyOrder($seatSubscriptions, $customer);
            }
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function createOrModifyOrder(Collection $subscriptions, Customer $customer): void
    {
        /** @var Subscription $child */
        $child = $subscriptions->first();
        $amount = $subscriptions->count();

        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $child->parent_subscription_id)->firstOrFail();
        $kpnCustomerNumber = $this->getKpnCustomerNumber($customer);

        if ($kpnCustomerNumber === null) {
            $this->logger->error(
                'M365 order missing KPN customer',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $microsoft365Deployment->subscription_id,
                    LoggingContextKeys::PROVISIONING_ID => $microsoft365Deployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                ]
            );

            $subscriptions->each(function (Subscription $subscription): void {
                $subscription->technical_status = TechnicalStatus::FAILED->value;
            });

            return;
        }

        if ($microsoft365Deployment->kpn_order_id !== null && $microsoft365Deployment->kpn_status === Microsoft365OrderStatus::ACTIVE) {
            try {
                $this->microsoft365Service->modifyOrder($microsoft365Deployment->kpn_order_id, $amount);
            } catch (Office365Exception $exception) {
                $this->logger->error(
                    'M365 order modification failed',
                    [
                        LoggingContextKeys::SUBSCRIPTION_ID => $microsoft365Deployment->subscription_id,
                        LoggingContextKeys::PROVISIONING_ID => $microsoft365Deployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                        LoggingContextKeys::CUSTOMER_ID => $customer->id,
                        LoggingContextKeys::EXCEPTION => $exception,
                        LoggingContextKeys::META => [
                            'kpn_order_id' => $microsoft365Deployment->kpn_order_id,
                            'microsoft365_customer_info_id' => $microsoft365Deployment->microsoft365CustomerInfo->id,
                            'amount' => $amount,
                        ],
                    ]
                );
            }
        } else {
            Assert::notNull($child->parent);
            $kpnProduct = $this->microsoft365KpnProductRepository->getBySubscription($child->parent);

            try {
                $customerInfo = $microsoft365Deployment->microsoft365CustomerInfo;
                if ($customerInfo->tenant_order_id === null) {
                    try {
                        $tenantOrderIdSynchronized = $this->microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($customerInfo);
                    } catch (OrderSummaryCustomerNotFoundException | OrderSummaryException $exception) {
                        $this->logger->error(
                            'M365 tenant order summary failed',
                            [
                                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                                LoggingContextKeys::PROVISIONING_ID => $microsoft365Deployment->id,
                                LoggingContextKeys::SUBSCRIPTION_ID => $microsoft365Deployment->subscription_id,
                                LoggingContextKeys::EXCEPTION => $exception,
                                LoggingContextKeys::META => [
                                    'microsoft365_customer_info_id' => $customerInfo->id,
                                ],
                            ]
                        );

                        return;
                    }

                    if (! $tenantOrderIdSynchronized) {
                        $this->microsoft365Service->createTenant(
                            microsoft365CustomerInfo: $customerInfo
                        );

                        // Creating tenant order is a queued API call, so we can immediately start creating the other orders
                        // Other orders will be created in the webhook callback when the tenant order is successful
                        return;
                    }
                }

                $this->microsoft365Service->createOrder(
                    microsoft365Deployment: $microsoft365Deployment,
                    productCode: $kpnProduct->kpn_product_code,
                    amount: $amount,
                );
            } catch (TenantNameTakenException | Office365Exception $exception) {
                $this->logger->error(
                    'M365 order creation failed',
                    [
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                        LoggingContextKeys::CUSTOMER_ID => $customer->id,
                        LoggingContextKeys::PROVISIONING_ID => $microsoft365Deployment->id,
                        LoggingContextKeys::SUBSCRIPTION_ID => $microsoft365Deployment->subscription_id,
                        LoggingContextKeys::EXCEPTION => $exception,
                        LoggingContextKeys::META => [
                            'tenant_name' => $microsoft365Deployment->microsoft365CustomerInfo->tenant_name,
                            'microsoft365_customer_info_id' => $microsoft365Deployment->microsoft365CustomerInfo->id,
                            'tenant_order_id' => $microsoft365Deployment->microsoft365CustomerInfo->tenant_order_id,
                        ],
                    ]
                );
            }
        }
    }

    private function getKpnCustomerNumber(Customer $customer): ?string
    {
        $customerInfo = Microsoft365CustomerInfo::where('customer_id', $customer->id)->firstOrFail();

        if ($customerInfo->technical_status === Microsoft365ProcessStatus::INITIATED) {
            return null;
        }

        return $customerInfo->kpn_customer_id;
    }

    private function checkFirstTimeCustomer(Customer $customer, string|null $tenantName): bool
    {
        return $tenantName === null
            ? Microsoft365CustomerInfo::where('customer_id', $customer->id)->doesntExist()
            : Microsoft365CustomerInfo::where('customer_id', $customer->id)->where('tenant_name', $tenantName)->doesntExist();
    }

    private function initiateFirstTimeCustomer(Customer $customer, string|null $tenantName, string|null $tenantId, Subscription $firstSubscription): void
    {
        $metaData = $firstSubscription->orderLineItem->meta_data ?? $firstSubscription->children()->first()?->orderLineItem?->meta_data;
        if ($metaData !== null) {
            $metaData = $this->microsoft365TenantService->getMicrosoft365MetaData($metaData);
        }

        /** @var Microsoft365CustomerInfo $customerInfo */
        $customerInfo = Microsoft365CustomerInfo::create([
            'customer_id' => $customer->id,
            'technical_status' => Microsoft365ProcessStatus::INITIATED,
            'tenant_name' => $tenantName ?? $this->formatTenantName($metaData?->tenantName),
            'tenant_id' => $tenantId ?? $metaData?->tenantId,
            'tenant_access_verified' => false,
            'type' => $metaData?->tenantId === null ? CustomerInfoType::REGISTER : CustomerInfoType::TRANSFER,
        ]);

        try {
            $successful = $this->microsoft365Service->createKpnCustomer($customer, (string) $customerInfo->id);

            if (! $successful) {
                $customerInfo->update(['technical_status' => Microsoft365ProcessStatus::FAILED]);
            }
        } catch (Office365Exception $exception) {
            $this->logger->error(
                'M365 KPN customer creation failed',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $customerInfo->id,
                        'tenant_id' => $customerInfo->tenant_id,
                    ],
                ]
            );

            $customerInfo->update(['technical_status' => Microsoft365ProcessStatus::FAILED]);
        }
    }

    private function formatTenantName(?string $tenantName): ?string
    {
        if (is_string($tenantName) && ! str_ends_with($tenantName, self::MICROSOFT_SUBDOMAIN)) {
            $tenantName .= self::MICROSOFT_SUBDOMAIN;
        }

        return $tenantName;
    }

    private function createParentSubscriptionIfNeeded(
        Subscription $childSubscription,
        Customer $customer,
        Microsoft365CustomerInfo $customerInfo,
        bool $firstTimeCustomer
    ): Subscription {
        $parentProduct = Product::where('slug', $childSubscription->product->slug . '-parent')->firstOrFail();

        // Cant check for archiving/expired here as the subscription can still be active in IRMA
        $parentSubscription = Subscription::where('product_uuid', $parentProduct->uuid)
            ->where('customer_id', $customer->id)
            ->where('contract_period', $childSubscription->contract_period)
            ->whereNotIn('administrative_status', [...AdministrativeStatus::administrativelyEnded(), AdministrativeStatus::ARCHIVING->value])

            ->first();

        if (! $parentSubscription instanceof Subscription || $firstTimeCustomer) {
            $parentSubscription = $this->subscriptionService->createFreeParentSubscription($childSubscription, $parentProduct);

            $invoice = $this->invoiceRepository->create(
                subscription: $parentSubscription,
                startDate: $parentSubscription->start_date,
                dispatchInvoiceCreated: false
            );

            $this->dispatcher->dispatch(
                new DispatchConsolidatedInvoicesForCustomer(
                    customer: $customer,
                    invoices: [$invoice],
                    createInvoiceInstantly: false,
                )
            );

            Microsoft365Deployment::create([
                'subscription_id' => $parentSubscription->id,
                'kpn_status' => Microsoft365OrderStatus::PLACED,
                'microsoft365_customer_info_id' => $customerInfo->id,
            ]);
        }

        return $parentSubscription;
    }

    /**
     * @param Collection<int, Subscription> $children
     */
    private function attachChildrenToParentSubscription(Collection $children, Subscription $parent): void
    {
        $children->map(function (Subscription $child) use ($parent): void {
            $child->parent_subscription_id = $parent->id;
            $child->end_date = $parent->end_date;
            $child->next_billing_date = $parent->next_billing_date;
            $child->save();
        });
    }
}
