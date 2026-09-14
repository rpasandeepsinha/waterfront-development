<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use Carbon\CarbonImmutable;
use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsRecord;
use SandwaveIo\Microsoft\Graph\Models\TenantInformation;
use SandwaveIo\Office365\Exception\Office365Exception;
use SandwaveIo\Office365\Office\OfficeClient;
use SandwaveIo\Office365\Response\CustomerAgreementAttestationResponse;
use SandwaveIo\Office365\Response\CustomerAgreementResponse;
use SandwaveIo\Office365\Response\OrderSummary;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Microsoft365\Dto\Microsoft365DeploymentDTO;
use Waterfront\Domain\Microsoft365\Dto\Microsoft365TenantInfoDTO;
use Waterfront\Domain\Microsoft365\Dto\NextInvoiceDTO;
use Waterfront\Domain\Microsoft365\Enums\KpnCountryCode;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerAgreementException;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365DeleteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetVerificationDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365SetDomainAsDefaultDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\DeleteDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\PromoteDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\ServiceDnsRecordsResult;
use Waterfront\Domain\Provision\Microsoft365\Results\SetDomainAsDefaultDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantIdResult;
use Waterfront\Domain\Provision\Microsoft365\Results\VerificationDnsRecordsResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

/**
 * @see \Tests\Domain\Microsoft365\Services\Microsoft365ServiceTest
 */
class Microsoft365Service
{
    public const string MICROSOFT_TENANT_PRODUCT_CODE = '282A00001B';

    private const string REFERENCE_FORMAT_CUSTOMER = 'WF-CUSTOMER-%s-%s';

    private const string REFERENCE_FORMAT_ORDER = 'WF-ORDER-%s-%s';

    private const string REFERENCE_FORMAT_TERMINATE = 'WF-TERMINATE-%s-%s';

    private const string MICROSOFT_HOSTNAME = '.onmicrosoft.com';

    public function __construct(
        private readonly OfficeClient $officeClient,
        private readonly GetNextInvoicePriceAction $nextInvoicePriceAction,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
        private readonly ProvisionGateway $provisionGateway,
        private readonly Microsoft365TenantService $microsoft365TenantService,
        private readonly GraphServiceClient $graphServiceClient,
        private readonly DnsService $dnsService,
        private readonly Microsoft365CustomerInfoRepository $customerInfoRepository,
        private readonly Microsoft365KpnProductRepository $microsoft365KpnProductRepository,
        private readonly Microsoft365Repository $microsoft365Repository,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
    ) {
    }

    /**
     * @throws Office365Exception|TooManyRequestsHttpException|JsonException
     */
    public function createKpnCustomer(Customer $customer, string $customerInfoId): bool
    {
        if (! $customer->address instanceof CustomerAddress) {
            $this->logger->info(
                sprintf(
                    "CreateKpnCustomer - customer id '%d' does not have a customer_address",
                    $customer->customer_number,
                ),
            );

            return false;
        }

        // These are the only country codes that KPN accepts.
        $countryCode = match ($customer->address->country_code) {
            'BE' => KpnCountryCode::BE,
            'DE' => KpnCountryCode::DE,
            'FR' => KpnCountryCode::FR,
            default => KpnCountryCode::NL,
        };

        $phoneNumber = $this->getPhoneNumberFromCustomer($customer);

        $partnerReference = sprintf(self::REFERENCE_FORMAT_CUSTOMER, $customer->id, $customerInfoId);

        try {
            $response = $this->officeClient->customer->create(
                name: $customer->organization ?? $customer->name,
                street: $customer->address->street_name,
                houseNr: intval($customer->address->street_number),
                houseNrExtension: $customer->address->getStreetNumberLetters(),
                zipCode: $customer->address->zip_code,
                city: $customer->address->city,
                countryCode: $countryCode->value,
                phone1: $phoneNumber,
                phone2: null,
                fax: null,
                email: $this->removePlusFromEmail($customer->email),
                website: null,
                debitNr: null,
                iban: null,
                bic: null,
                vatNr: null,
                legalStatus: 'Onbekend',
                externalId: null,
                chamberOfCommerceNr: null,
                partnerReference: $partnerReference,
            );

            $successful = $response->isSuccess();

            if (! $successful) {
                $this->logger->error(
                    sprintf(
                        'CreateKpnCustomer - KPN error code: %d, message: %s, details: %s',
                        $response->getErrorCode(),
                        $response->getErrorMessage(),
                        json_encode($response->getErrorDetails(), JSON_THROW_ON_ERROR),
                    ),
                );
            }

            return $successful;
        } catch (Office365Exception $e) {
            if (stripos($e->getMessage(), 'Too Many Requests') !== false) {
                $this->logger->error(
                    sprintf(
                        'CreateKpnCustomer - KPN rate limit reached when creating KPN customer for customer_id %d',
                        $customer->customer_number,
                    ),
                );

                throw new TooManyRequestsHttpException(null, 'KPN rate limit reached for this endpoint.', $e);
            }

            $this->logger->error(
                sprintf(
                    'CreateKpnCustomer - KPN office package exception with message: %s',
                    $e->getMessage(),
                ),
            );
        }

        return false;
    }

    /**
     * @throws JsonException|Office365Exception|TenantNameTakenException
     */
    public function createTenant(
        Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ): bool {
        $microsoft365CustomerInfo->loadMissing([
            'customer',
            'microsoft365Deployments.subscription.children.orderLineItem',
            'microsoft365Deployments.subscription.orderLineItem',
        ]);

        $customerInfoMeta = $this->getCustomerInfoMeta($microsoft365CustomerInfo);

        $this->logger->debug(
            sprintf(
                'CreateTenant - Start creating tenant for customer_id: %d',
                $microsoft365CustomerInfo->customer->id,
            ),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                LoggingContextKeys::META => [
                    'microsoft365_customer_info' => $customerInfoMeta,
                ],
            ],
        );

        $partnerReference = sprintf(
            self::REFERENCE_FORMAT_CUSTOMER,
            $microsoft365CustomerInfo->customer->id,
            $microsoft365CustomerInfo->id,
        );
        $tenantName = $microsoft365CustomerInfo->tenant_name;
        $tenantId = $microsoft365CustomerInfo->tenant_id;
        $subscription = $microsoft365CustomerInfo->microsoft365Deployments->first()?->subscription;
        Assert::notNull($subscription);
        $firstChild = $subscription->children->first();
        $metaData = $subscription->orderLineItem->meta_data ?? $firstChild?->orderLineItem?->meta_data;

        if ($tenantName === null && $metaData !== null) {
            $metaData = $this->microsoft365TenantService->getMicrosoft365MetaData($metaData);
            $tenantName = $this->addHostToTenant($metaData->tenantName);
            $tenantId = $metaData->tenantId;
        }

        /*
         * Generating the tenant name should not be removed because of orders placed before the tenant form.
         * This will otherwise cause issues when trying to (re)provision these deployments
         */
        if ($tenantName === null) {
            for ($i = 0; $i < 5; $i++) {
                $tenantName = $this->generateTenantName($microsoft365CustomerInfo->customer, (bool) $i);

                if ($this->isTenantNameTaken($tenantName)) {
                    $this->logger->error(
                        sprintf("CreateTenant - The tenant name '%s' is already taken.", $tenantName),
                        [
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                            LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                            LoggingContextKeys::META => [
                                'microsoft365_customer_info' => $customerInfoMeta,
                            ],
                        ],
                    );
                    if ($i === 4) {
                        $this->logger->error(
                            sprintf(
                                "CreateTenant - Reached the 5 try limit when creating a tenant for customer_id: '%d'",
                                $microsoft365CustomerInfo->customer->customer_number,
                            ),
                            [
                                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                                LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                                LoggingContextKeys::META => [
                                    'microsoft365_customer_info' => $customerInfoMeta,
                                ],
                            ],
                        );

                        return false;
                    }
                } else {
                    break;
                }
            }
        }

        assert(is_string($tenantName));

        $response = $this->officeClient->order->tenant->create(
            customerId: Microsoft365Helper::customerIdToInt($microsoft365CustomerInfo->kpn_customer_id),
            productCode: self::MICROSOFT_TENANT_PRODUCT_CODE,
            IsExistingTenant: $tenantId !== null,
            tenantName: $tenantName,
            tenantId: $tenantId,
            firstName: $microsoft365CustomerInfo->customer->first_name,
            lastName: $microsoft365CustomerInfo->customer->last_name,
            email: $this->removePlusFromEmail($microsoft365CustomerInfo->customer->email),
            partnerReference: $partnerReference,
        );

        $successful = $response->isSuccess();

        if (! $successful) {
            $this->logger->error(
                sprintf(
                    'CreateTenant - KPN error code: %d, message: %s, details: %s',
                    $response->getErrorCode(),
                    $response->getErrorMessage(),
                    json_encode($response->getErrorDetails(), JSON_THROW_ON_ERROR),
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info' => $customerInfoMeta,
                    ],
                ],
            );
        }

        return $successful;
    }

    /**
     * @throws Office365Exception|JsonException
     */
    public function createOrder(
        Microsoft365Deployment $microsoft365Deployment,
        string $productCode,
        int $amount,
    ): bool {
        $microsoft365CustomerInfo = $microsoft365Deployment->microsoft365CustomerInfo;
        $customerInfoMeta = $this->getCustomerInfoMeta($microsoft365CustomerInfo);

        $this->logger->debug(
            sprintf('CreateOrder - Start creating order for customer_id: %d', $microsoft365CustomerInfo->customer->id),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                LoggingContextKeys::META => [
                    'microsoft365_customer_info' => $customerInfoMeta,
                ],
            ],
        );

        $response = $this->officeClient->order->cloudLicense->create(
            // KPN sends the ID like this 'CID123456' but for this endpoint expects it back like '123456'.
            customerId: Microsoft365Helper::customerIdToInt($microsoft365CustomerInfo->kpn_customer_id),
            productCode: $productCode,
            quantity: $amount,
            partnerReference: sprintf(
                self::REFERENCE_FORMAT_ORDER,
                $microsoft365CustomerInfo->id,
                $microsoft365Deployment->id,
            ),
        );

        $successful = $response->isSuccess();

        if (! $successful) {
            $this->logger->error(
                sprintf(
                    'CreateOrder - KPN error code: %d, message: %s, details: %s',
                    $response->getErrorCode(),
                    $response->getErrorMessage(),
                    json_encode($response->getErrorDetails(), JSON_THROW_ON_ERROR),
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info' => $customerInfoMeta,
                    ],
                ],
            );
        }

        return $successful;
    }

    /**
     * @throws Office365Exception
     */
    public function modifyOrder(int $orderId, int $amount): bool
    {
        $response = $this->officeClient->order->modify(orderId: $orderId, quantity: $amount, isDelta: true);

        $successful = $response->isSuccess();

        if (! $successful) {
            $this->logger->error(
                sprintf(
                    'CreateOrder - KPN error code: %d, message: %s',
                    $response->getErrorCode(),
                    $response->getErrorMessage(),
                ),
            );
        }

        return $successful;
    }

    /**
     * @throws OrderSummaryCustomerNotFoundException
     * @throws OrderSummaryException
     *
     * @return array<int, OrderSummary>
     */
    public function orderSummary(
        ?int $customer = null,
        ?string $orderState = null,
        ?string $productGroup = null,
        ?string $productName = null,
        ?DateTime $dateActiveFrom = null,
        ?DateTime $dateActiveTo = null,
        ?DateTime $dateModifiedFrom = null,
        ?DateTime $dateModifiedTo = null,
        ?string $label = null,
        ?string $attribute = null,
        ?int $skip = null,
        ?int $take = null,
    ): array {
        try {
            $response = $this->officeClient->order->summary(
                customerId: $customer,
                orderState: $orderState,
                productGroup: $productGroup,
                productName: $productName,
                dateActiveFrom: $dateActiveFrom,
                dateActiveTo: $dateActiveTo,
                dateModifiedFrom: $dateModifiedFrom,
                dateModifiedTo: $dateModifiedTo,
                label: $label,
                attribute: $attribute,
                skip: $skip,
                take: $take,
            );
        } catch (Office365Exception $e) {
            $this->logger->error(
                sprintf(
                    'OrderSummary - KPN office package exception with message: %s',
                    $e->getMessage(),
                ),
            );

            throw new OrderSummaryException('Something went wrong while retrieving order summary.', 0, $e);
        }

        if (in_array(sprintf('CustomerId %d not found', $customer), $response->getStatus()->getMessages(), true)) {
            throw new OrderSummaryCustomerNotFoundException(
                'Something went wrong while retrieving order summary.',
                0,
            );
        }

        return $response->getPagedResult()->getResults();
    }

    /**
     * @throws OrderSummaryCustomerNotFoundException
     * @throws OrderSummaryException
     */
    public function synchronizeTenantOrderIdFromOrderSummary(Microsoft365CustomerInfo $microsoft365CustomerInfo): bool
    {
        if ($microsoft365CustomerInfo->tenant_order_id !== null) {
            return true;
        }

        $tenantOrderId = $this->getTenantOrderId(
            customerId: Microsoft365Helper::customerIdToInt($microsoft365CustomerInfo->kpn_customer_id),
        );

        if ($tenantOrderId === null) {
            return false;
        }

        $microsoft365CustomerInfo->tenant_order_id = $tenantOrderId;
        $microsoft365CustomerInfo->save();

        return true;
    }

    /**
     * Retrieves the tenant order ID for a given KPN customer from their order summary.
     *
     * @throws OrderSummaryCustomerNotFoundException
     * @throws OrderSummaryException
     */
    public function getTenantOrderId(int $customerId): ?int
    {
        try {
            $response =
                $this->officeClient->order->summary(
                    customerId: $customerId,
                );
        } catch (Office365Exception $e) {
            $this->logger->error(
                sprintf(
                    'GetTenantOrderId - KPN office package exception with message: %s',
                    $e->getMessage(),
                ),
            );

            throw new OrderSummaryException('Something went wrong while retrieving tenant order ID.', 0, $e);
        }

        if (in_array(sprintf('CustomerId %d not found', $customerId), $response->getStatus()->getMessages(), true)) {
            throw new OrderSummaryCustomerNotFoundException(
                'Something went wrong while retrieving tenant order ID.',
                0,
            );
        }

        foreach ($response->getPagedResult()->getResults() as $summary) {
            if ($summary->getProductId() === self::MICROSOFT_TENANT_PRODUCT_CODE) {
                if (strtolower($summary->getOrderState()) !== Microsoft365OrderStatus::ACTIVE->value) {
                    $this->logger->info(
                        'Tenant order found but not yet active',
                        [
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                            LoggingContextKeys::ORDER_ID => $summary->getOrderId(),
                            LoggingContextKeys::META => [
                                'order_state' => $summary->getOrderState(),
                            ],
                        ],
                    );

                    return null;
                }

                return $summary->getOrderId();
            }
        }

        return null;
    }

    /**
     * @throws TenantNameTakenException
     */
    public function isTenantNameTaken(string $tenantName): bool
    {
        try {
            $response = $this->officeClient->tenant->exists($tenantName);
        } catch (Office365Exception $exception) {
            $this->logger->error(sprintf(
                'TenantExists gave the following exception (%s).',
                $exception->getMessage(),
            ));

            throw new TenantNameTakenException('Something went wrong while checking tenant names.', 0, $exception);
        }

        return $response->isExistingTenant();
    }

    /**
     * @throws Office365Exception
     */
    public function terminateOrder(Microsoft365Deployment $microsoft365Deployment): bool
    {
        $response = $this->officeClient->order->terminate(
            orderId: (string) $microsoft365Deployment->kpn_order_id,
            desiredTerminateDate: CarbonImmutable::now()->subDays(4)->toDateTime(),
            terminateAsSoonAsPossible: true,
            partnerReference: sprintf(
                self::REFERENCE_FORMAT_TERMINATE,
                $microsoft365Deployment->microsoft365CustomerInfo->id,
                $microsoft365Deployment->kpn_order_id,
            ),
        );

        $successful = $response->isSuccess();
        if (! $successful) {
            $this->logger->error(
                sprintf(
                    'TerminateOrder - KPN error code: %d, message: %s',
                    $response->getErrorCode(),
                    $response->getErrorMessage(),
                ),
            );
        }

        return $successful;
    }

    public function gatherMicrosoftData(Customer $customer): ?Microsoft365TenantInfoDTO
    {
        $customerInfo = $this->customerInfoRepository->findByCustomer($customer);
        if (! $customerInfo instanceof Microsoft365CustomerInfo) {
            return null;
        }

        Assert::notNull($customerInfo->tenant_name);
        $primaryDomain = $customerInfo->primary_domain ?? $customerInfo->tenant_name;

        $microsoft365TenantInfo = new Microsoft365TenantInfoDTO(
            coupledDomainSubscriptionUuid: $this->domainDeploymentRepository->getDeploymentByDomainAndCustomer(
                $primaryDomain,
                $customer,
            )?->subscription->uuid,
            tenantId: $customerInfo->tenant_id,
            tenantName: $customerInfo->tenant_name,
            primaryDomain: $primaryDomain,
            primaryDomainStatus: $customerInfo->primary_domain_status,
            availableActions: $customerInfo->mca_signed_at === null ? ['sign_mca'] : [],
        );

        $microsoftSubscriptions = $customer
            ->subscriptions()
            ->whereNull('parent_subscription_id')
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::ARCHIVING->value,
            ])
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::MICROSOFT_365);
            })
            ->get();

        /** @var Subscription $subscription */
        foreach ($microsoftSubscriptions as $subscription) {
            $childrenCount = $subscription
                ->children
                ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                ->count();
            $canceledChildrenCount = $subscription
                ->children
                ->where('administrative_status', AdministrativeStatus::CANCELED->value)
                ->count();

            $microsoft365Deployment = new Microsoft365DeploymentDTO(
                id: $subscription->id,
                subscriptionUuid: $subscription->uuid,
                productName: str_replace(' Administrative', '', $subscription->product->name),
                productSlug: str_replace('-parent', '', $subscription->product->slug),
                administrativeStatus: $subscription->administrative_status,
                technicalStatus: $subscription->technical_status,
                startDate: $subscription->start_date,
                endDate: $subscription->end_date,
                period: $subscription->contract_period,
                contractPeriod: $subscription->contract_period,
                billingPeriod: $subscription->billing_period,
                seatCount: $childrenCount,
                canceledSeatCount: $canceledChildrenCount,
                nextInvoice: new NextInvoiceDTO(
                    date: $subscription->next_billing_date,
                    price: $this->nextInvoicePriceAction->execute($subscription)->netPrice,
                ),
            );

            $microsoft365TenantInfo->addDeployment($microsoft365Deployment);
        }

        return $microsoft365TenantInfo;
    }

    public function generateTenantName(Customer $customer, bool $retry = false): string
    {
        $tenantPrefix = $this->configuration->getAsString('microsoft365.tenant_prefix');

        if ($retry) {
            return $this->addHostToTenant(
                $tenantPrefix . $customer->id . 'r' . strtolower(Str::random(5)),
            );
        }

        return $this->addHostToTenant($tenantPrefix . $customer->id);
    }

    public function modifyKpnCustomer(
        string $customerId,
        string $name,
        string $street,
        int $houseNr,
        ?string $houseNrExtension,
        string $zipCode,
        string $city,
        string $countryCode,
        string $phone1,
        ?string $email,
        string $partnerReference,
    ): bool {
        try {
            $response = $this->officeClient->customer->modify(
                customerId: $customerId,
                name: $name,
                street: $street,
                houseNr: $houseNr,
                houseNrExtension: $houseNrExtension,
                zipCode: $zipCode,
                city: $city,
                countryCode: $countryCode,
                phone1: $phone1,
                phone2: null,
                fax: null,
                email: $email,
                website: null,
                debitNr: null,
                iban: null,
                bic: null,
                vatNr: null,
                legalStatus: 'Onbekend',
                externalId: null,
                chamberOfCommerceNr: null,
                partnerReference: $partnerReference,
            );

            $successful = $response->isSuccess();

            if (! $successful) {
                $this->logger->error(
                    sprintf(
                        'ModifyKpnCustomer - KPN error code: %d, message: %s, details: %s',
                        $response->getErrorCode(),
                        $response->getErrorMessage(),
                        json_encode($response->getErrorDetails(), JSON_THROW_ON_ERROR),
                    ),
                );
            }

            return $successful;
        } catch (Office365Exception $e) {
            $this->logger->error(
                sprintf(
                    'ModifyKpnCustomer - KPN office package exception with message: %s',
                    $e->getMessage(),
                ),
            );
        }

        return false;
    }

    public function getTenantIdByName(string $tenantName): ?string
    {
        $tenantName = $this->addHostToTenant($tenantName);

        $tenantIdResult = $this->provisionGateway->request(
            new Microsoft365TenantIdRequest($tenantName, Str::uuid()),
        );

        Assert::isInstanceOf($tenantIdResult, TenantIdResult::class);

        if ($tenantIdResult->failed) {
            $this->logger->warning(
                'Retrieving tenant id from name [meta.tenant_name] failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_ONLINE,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::META => [
                        'tenant_name' => $tenantName,
                    ],
                    LoggingContextKeys::EXCEPTION => $tenantIdResult->exception,
                ],
            );

            return null;
        }

        return $tenantIdResult->tenantId;
    }

    public function removePlusFromEmail(string $email): string
    {
        return (string) preg_replace('/\+[^@]*/i', '', $email);
    }

    public function hasDomainOwnership(string $tenantId): bool
    {
        $ownership = $this->officeClient->customer->hasTenantDomainOwnership(
            $this->configuration->getAsInteger('microsoft365.customer_placeholder_id'),
            $tenantId,
        );

        return $ownership->getIsDelegatedAccessAllowed();
    }

    public function getTenantDefaultDomainName(string $tenantName): string
    {
        $tenant = $this->graphServiceClient
            ->tenantRelationships()
            ->findTenantInformationByDomainNameWithDomainName($tenantName)
            ->get()
            ->wait();

        Assert::isInstanceOf($tenant, TenantInformation::class);

        $defaultDomainName = $tenant->getDefaultDomainName();

        Assert::notNull($defaultDomainName);

        return $defaultDomainName;
    }

    public function checkIfDomainExistsInMicrosoftAccount(
        string $domain,
        string $tenantId,
        Subscription $subscription,
    ): bool {
        $getDomainRequest = new Microsoft365GetDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $getDomainResult = $this->provisionGateway->request($getDomainRequest);

        return $getDomainResult->succeeded;
    }

    public function createDomainInMicrosoftAccount(string $domain, string $tenantId, Subscription $subscription): bool
    {
        $createDomainRequest = new Microsoft365CreateDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $createDomainResult = $this->provisionGateway->request($createDomainRequest);

        return $createDomainResult->succeeded;
    }

    public function verifyDomainInMicrosoftAccount(string $domain, string $tenantId, Subscription $subscription): bool
    {
        $verifyDomainRequest = new Microsoft365VerifyDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $verifyDomainResult = $this->provisionGateway->request($verifyDomainRequest);

        return $verifyDomainResult->succeeded;
    }

    public function promoteDomainInMicrosoftAccount(string $domain, string $tenantId, Subscription $subscription): bool
    {
        $promoteDomainRequest = new Microsoft365PromoteDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $promoteDomainResult = $this->provisionGateway->request($promoteDomainRequest);

        return $promoteDomainResult instanceof PromoteDomainResult && $promoteDomainResult->succeeded;
    }

    public function deleteDomainInMicrosoftAccount(string $domain, string $tenantId, Subscription $subscription): bool
    {
        $deleteDomainRequest = new Microsoft365DeleteDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $deleteDomainResult = $this->provisionGateway->request($deleteDomainRequest);

        return $deleteDomainResult instanceof DeleteDomainResult && $deleteDomainResult->succeeded;
    }

    public function setDomainAsDefaultDomainInMicrosoftAccount(
        string $domain,
        string $tenantId,
        Subscription $subscription,
    ): bool {
        $setDefaultDomainRequest = new Microsoft365SetDomainAsDefaultDomainRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $setDomainAsDefaultDomainResult = $this->provisionGateway->request($setDefaultDomainRequest);

        return (
            $setDomainAsDefaultDomainResult instanceof SetDomainAsDefaultDomainResult
            && $setDomainAsDefaultDomainResult->succeeded
        );
    }

    public function setServiceConfigurationRecordsForPrimaryDomain(
        string $domain,
        string $tenantId,
        Subscription $subscription,
    ): bool {
        $serviceDnsRequest = new Microsoft365GetServiceDnsRecordsRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $serviceDnsResult = $this->provisionGateway->request($serviceDnsRequest);

        if (! $serviceDnsResult instanceof ServiceDnsRecordsResult || $serviceDnsResult->failed) {
            return false;
        }

        try {
            // @phpstan-ignore argument.type (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
            $this->deleteExistingMxRecords($domain, $serviceDnsResult->records);

            // @phpstan-ignore argument.type (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
            return $this->updateDnsRecordsForPrimaryDomain($serviceDnsResult->records, $domain);
        } catch (JsonException|GuzzleException|PdnsResponseException|DnsZoneNotFoundException $exception) {
            $this->logger->error(
                'Error while setting service DNS records for primary domain {domain.name}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_GRAPH,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ],
            );

            return false;
        }
    }

    public function setVerificationDnsRecordsForPrimaryDomain(
        string $domain,
        string $tenantId,
        Subscription $subscription,
    ): bool {
        $verificationDnsRequest = new Microsoft365GetVerificationDnsRecordsRequest(
            domainName: $domain,
            context: Uuid::fromString($tenantId),
            tagUuid: Uuid::fromString($subscription->uuid),
        );
        $verificationDnsResult = $this->provisionGateway->request($verificationDnsRequest);

        if (! $verificationDnsResult instanceof VerificationDnsRecordsResult || $verificationDnsResult->failed) {
            return false;
        }

        try {
            // @phpstan-ignore argument.type (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
            return $this->updateDnsRecordsForPrimaryDomain($verificationDnsResult->records, $domain);
        } catch (JsonException|GuzzleException|PdnsResponseException|DnsZoneNotFoundException $exception) {
            $this->logger->error(
                'Error while setting verification DNS records for primary domain {domain.name}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_GRAPH,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ],
            );

            return false;
        }
    }

    /**
     * @throws MicrosoftCustomerNotFoundException
     * @throws MicrosoftCustomerAgreementException
     * @throws Office365Exception
     */
    public function getMicrosoftCustomerAgreementUrl(Customer $customer): CustomerAgreementAttestationResponse
    {
        $customerInfo = $customer->microsoft365CustomerInfo()->first();
        if (! $customerInfo instanceof Microsoft365CustomerInfo || $customerInfo->kpn_customer_id === null) {
            throw new MicrosoftCustomerNotFoundException(
                message: 'Microsoft Customer has not yet been created or customer creation webhook has not been triggered yet.',
            );
        }

        $kpnCustomerId = Microsoft365Helper::customerIdToInt($customerInfo->kpn_customer_id);

        $phoneNumber = $this->getPhoneNumberFromCustomer($customer);

        // Temporary fix for non Dutch phone numbers until KPN fixes their regex, this is YH phone number
        if (! str_starts_with($phoneNumber, '0031')) {
            $phoneNumber = '0031388509600';
        }

        try {
            return $this->officeClient->customer->getAttestationUrl(
                customerId: $kpnCustomerId,
                email: $this->removePlusFromEmail($customer->email),
                phone: $phoneNumber,
                companyName: $this->getCompanyNameForAgreement($customer),
                name: $this->getNameForAgreement($customer),
                firstName: $customer->first_name,
                lastName: $customer->last_name,
            );
        } catch (Office365Exception $exception) {
            $this->logger->error(
                'Error while retrieving microsoft customer agreement url for customer {customer.id}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'kpn_customer_id' => $kpnCustomerId,
                        'customer_info_id' => $customerInfo->id,
                        'tenant_id' => $customerInfo->tenant_id,
                        'tenant_name' => $customerInfo->tenant_name,
                        'email' => $this->removePlusFromEmail($customer->email),
                        'phone' => $this->getPhoneNumberFromCustomer($customer),
                        'companyName' => $this->getCompanyNameForAgreement($customer),
                        'name' => $this->getNameForAgreement($customer),
                        'firstName' => $customer->first_name,
                        'lastName' => $customer->last_name,
                    ],
                ],
            );
            throw new MicrosoftCustomerAgreementException(
                message: sprintf(
                    'Could not retrieve microsoft customer agreement url for customer, exception: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @throws MicrosoftCustomerNotFoundException
     * @throws MicrosoftCustomerAgreementException
     */
    public function getMicrosoftCustomerAgreement(Customer $customer): CustomerAgreementResponse
    {
        $customerInfo = $customer->microsoft365CustomerInfo()->first();
        if (! $customerInfo instanceof Microsoft365CustomerInfo || $customerInfo->kpn_customer_id === null) {
            throw new MicrosoftCustomerNotFoundException(
                message: 'Microsoft Customer has not yet been created or customer creation webhook has not been triggered yet.',
            );
        }

        $kpnCustomerId = Microsoft365Helper::customerIdToInt($customerInfo->kpn_customer_id);

        try {
            return $this->officeClient->customer->getCustomerAgreement($kpnCustomerId);
        } catch (Office365Exception $exception) {
            $this->logger->error(
                'Error while retrieving microsoft customer agreement for customer {customer.id}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'kpn_customer_id' => $kpnCustomerId,
                        'customer_info_id' => $customerInfo->id,
                        'tenant_id' => $customerInfo->tenant_id,
                        'tenant_name' => $customerInfo->tenant_name,
                    ],
                ],
            );
            throw new MicrosoftCustomerAgreementException(
                message: sprintf(
                    'Could not retrieve microsoft customer agreement for customer, exception: %s',
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    public function prepareOrders(Microsoft365CustomerInfo $microsoft365CustomerInfo): void
    {
        Assert::notNull($microsoft365CustomerInfo->kpn_customer_id);

        $microsoft365CustomerInfo->loadMissing([
            'customer',
            'microsoft365Deployments.subscription.children',
            'microsoft365Deployments.subscription.product',
        ]);

        /** @var Collection<int, Microsoft365Deployment> $microsoft365Deployments */
        $microsoft365Deployments = $microsoft365CustomerInfo->microsoft365Deployments->filter(fn (Microsoft365Deployment $microsoft365Deployment) => ! in_array(
            $microsoft365Deployment->subscription->administrative_status,
            [...AdministrativeStatus::administrativelyEnded(), AdministrativeStatus::ARCHIVING->value],
            true,
        ));

        if ($microsoft365Deployments->isEmpty()) {
            $this->logger->error(
                sprintf(
                    'deployments could not be found for customer info: [%d].',
                    $microsoft365CustomerInfo->id,
                ),
            );

            return;
        }

        if ($microsoft365CustomerInfo->tenant_order_id === null) {
            try {
                $tenantOrderIdSynchronized = $this->synchronizeTenantOrderIdFromOrderSummary($microsoft365CustomerInfo);
            } catch (OrderSummaryCustomerNotFoundException|OrderSummaryException $exception) {
                $this->logger->error(
                    'Tenant order summary retrieval failed',
                    [
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                        LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer->id,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ],
                );

                return;
            }

            if (! $tenantOrderIdSynchronized) {
                $this->createTenant(
                    microsoft365CustomerInfo: $microsoft365CustomerInfo,
                );

                return;
            }
        }

        $microsoft365Deployments->each(function (Microsoft365Deployment $microsoft365Deployment): void {
            $parentSubscription = $microsoft365Deployment->subscription;
            try {
                $kpnProduct = $this->microsoft365KpnProductRepository->getBySubscription($parentSubscription);
            } catch (ModelNotFoundException) {
                $this->logger->error(
                    sprintf(
                        'KPN product for product %d with contract period %d could not be found.',
                        $parentSubscription->product->slug,
                        $parentSubscription->contract_period,
                    ),
                );
                $parentSubscription->technical_status = TechnicalStatus::FAILED->value;
                $parentSubscription->save();

                return;
            }

            $this->createOrder(
                microsoft365Deployment: $microsoft365Deployment,
                productCode: $kpnProduct->kpn_product_code,
                amount: $parentSubscription
                    ->children
                    ->whereNotIn('administrative_status', [
                        ...AdministrativeStatus::administrativelyEnded(),
                        AdministrativeStatus::ARCHIVING->value,
                    ])
                    ->count(),
            );
        });
    }

    public function retryPendingCopilotOrder(Microsoft365CustomerInfo $microsoft365CustomerInfo): void
    {
        if ($microsoft365CustomerInfo->kpn_customer_id === null) {
            $this->logger->warning(
                'Pending Copilot retry skipped, missing KPN customer id',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $microsoft365CustomerInfo->id,
                    ],
                ],
            );

            return;
        }

        $pendingCopilotDeployment =
            $this->microsoft365Repository->findPendingCopilotDeploymentForRetry($microsoft365CustomerInfo);

        if ($pendingCopilotDeployment === null) {
            return;
        }

        if (! $this->microsoft365Repository->hasActiveCopilotPrerequisite($microsoft365CustomerInfo)) {
            $this->logger->info(
                'Pending Copilot retry skipped missing active prerequisite',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $microsoft365CustomerInfo->id,
                    ],
                ],
            );

            return;
        }

        $activeChildCount = $this->microsoft365Repository->activeChildrenCount($pendingCopilotDeployment);

        try {
            $pendingCopilotDeployment->loadMissing('microsoft365CustomerInfo.customer');

            $kpnProduct = $this->microsoft365KpnProductRepository->getBySubscription($pendingCopilotDeployment->subscription);

            $this->logger->info(
                'Retrying pending Copilot order',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $pendingCopilotDeployment->subscription_id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $microsoft365CustomerInfo->id,
                        'microsoft365_deployment_id' => $pendingCopilotDeployment->id,
                        'seat_count' => $activeChildCount,
                    ],
                ],
            );

            $successful = $this->createOrder(
                microsoft365Deployment: $pendingCopilotDeployment,
                productCode: $kpnProduct->kpn_product_code,
                amount: $activeChildCount,
            );

            if ($successful) {
                $pendingCopilotDeployment->kpn_status = Microsoft365OrderStatus::ACCEPTED;
                $pendingCopilotDeployment->save();

                return;
            }

            $this->logger->error(
                'Pending Copilot retry failed unsuccessful response',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $pendingCopilotDeployment->subscription_id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $microsoft365CustomerInfo->id,
                        'microsoft365_deployment_id' => $pendingCopilotDeployment->id,
                    ],
                ],
            );
        } catch (JsonException|ModelNotFoundException|Office365Exception|TenantNameTakenException $exception) {
            $this->logger->error(
                'Pending Copilot retry failed',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $pendingCopilotDeployment->subscription_id,
                    LoggingContextKeys::META => [
                        'microsoft365_customer_info_id' => $microsoft365CustomerInfo->id,
                        'microsoft365_deployment_id' => $pendingCopilotDeployment->id,
                    ],
                ],
            );
        }
    }

    /**
     * @throws Office365Exception
     */
    private function getPhoneNumberFromCustomer(Customer $customer): string
    {
        $phoneNumber =
            '00' . $customer->phone_country_code . $customer->phone_area_code . $customer->phone_subscriber_number;
        if ($customer->getPhoneNumberAttribute() === '') {
            $phoneNumber = $this->configuration->getAsString('microsoft365.default_company_phone_number');
            if ($phoneNumber === '') {
                throw new Office365Exception(
                    'CreateKpnCustomer - No phone number found for KPN customer.',
                );
            }
        }

        return $phoneNumber;
    }

    /**
     * @param array<DomainDnsRecord> $records
     *
     * @throws GuzzleException
     * @throws JsonException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     */
    private function updateDnsRecordsForPrimaryDomain(array $records, string $domain): bool
    {
        $powerDnsRecords = $this->dnsService->getDnsRecordsForDomain($domain);

        foreach ($records as $record) {
            if ($record->getRecordType() === null) {
                continue;
            }

            if ($record->getSupportedService() !== 'Email') {
                continue;
            }

            Assert::notNull($record->getRecordType());
            Assert::notNull($record->getLabel());
            Assert::notNull($record->getTtl());
            $dnsRecord = match ($record->getRecordType()) {
                'Txt' => new DefaultRecord(
                    type: strtoupper($record->getRecordType()),
                    name: $record->getLabel(),
                    content: $record->getText(), // @phpstan-ignore-line does exist as it extends it
                    ttl: $record->getTtl() ?? 3600,
                ),
                'Mx' => new MxRecord(
                    name: $record->getLabel(),
                    content: $record->getMailExchange(), // @phpstan-ignore-line does exist as it extends it
                    priority: (int) $record->getPreference(), // @phpstan-ignore-line does exist as it extends it
                    ttl: $record->getTtl() ?? 3600,
                ),
                'CName' => new CnameRecord(
                    name: $record->getLabel(),
                    content: $record->getCanonicalName(), // @phpstan-ignore-line does exist as it extends it
                    ttl: $record->getTtl() ?? 3600,
                ),
                default => null,
            };

            if ($dnsRecord === null) {
                continue;
            }

            $dnsRecords = $powerDnsRecords->filter(
                fn (DnsRecordInterface $powerDnsRecord) => (
                    strtolower($powerDnsRecord->getType()) === strtolower($dnsRecord->getType())
                    && $powerDnsRecord->getName() === $dnsRecord->getName()
                    && $powerDnsRecord->getContent() === $dnsRecord->getContent()
                ),
            );

            if ($dnsRecords->count() > 0) {
                continue;
            }

            $this->dnsService->addRecordFromObject(
                $domain,
                $dnsRecord,
            );
        }

        return true;
    }

    /**
     * @param array<DomainDnsRecord> $records
     *
     * @throws GuzzleException
     * @throws JsonException
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     */
    private function deleteExistingMxRecords(string $domain, array $records): void
    {
        $powerDnsRecords = $this->dnsService->getDnsRecordsForDomain($domain);

        $powerDnsRecordsRemove = $powerDnsRecords->filter(fn (DnsRecordInterface $record) => array_any(
            $records,
            fn ($domainDnsRecord) => (
                strtolower($record->getType()) !== strtolower($domainDnsRecord->getRecordType() ?? '')
                && $record->getName() !== $domainDnsRecord->getLabel()
            ),
        ));

        foreach ($powerDnsRecordsRemove as $powerDnsRecord) {
            if ($powerDnsRecord->getType() === DnsRecordType::MX->value) {
                $this->dnsService->removeDnsRecord($domain, $powerDnsRecord);
            }
        }
    }

    private function addHostToTenant(string $tenantName): string
    {
        if (! str_ends_with($tenantName, self::MICROSOFT_HOSTNAME)) {
            $tenantName = sprintf('%s%s', $tenantName, self::MICROSOFT_HOSTNAME);
        }

        return $tenantName;
    }

    private function getNameForAgreement(Customer $customer): string
    {
        if (strlen($customer->name) > 0) {
            return $customer->name;
        }

        if (strlen($customer->last_name) > 0) {
            return $customer->last_name;
        }

        return $customer->first_name;
    }

    private function getCompanyNameForAgreement(Customer $customer): string
    {
        $name = $customer->organization ?? $customer->name;
        if (strlen($name) > 0) {
            return $name;
        }

        if (strlen($customer->last_name) > 0) {
            return $customer->last_name;
        }

        return $customer->first_name;
    }

    /**
     * @return array<string, null|string|int|bool|CarbonImmutable>
     */
    private function getCustomerInfoMeta(Microsoft365CustomerInfo $microsoft365CustomerInfo): array
    {
        return [
            'id' => $microsoft365CustomerInfo->id,
            'customer_id' => $microsoft365CustomerInfo->customer_id,
            'type' => $microsoft365CustomerInfo->type->value,
            'technical_status' => $microsoft365CustomerInfo->technical_status->value,
            'tenant_name' => $microsoft365CustomerInfo->tenant_name,
            'tenant_order_id' => $microsoft365CustomerInfo->tenant_order_id,
            'kpn_customer_id' => $microsoft365CustomerInfo->kpn_customer_id,
            'tenant_id' => $microsoft365CustomerInfo->tenant_id,
            'tenant_access_verified' => $microsoft365CustomerInfo->tenant_access_verified,
            'primary_domain' => $microsoft365CustomerInfo->primary_domain,
            'primary_domain_status' => $microsoft365CustomerInfo->primary_domain_status?->value,
            'synced_at' => $microsoft365CustomerInfo->synced_at,
        ];
    }
}
