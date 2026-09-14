<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Sentry\Tracing\GuzzleTracingMiddleware;
use stdClass;
use Waterfront\Apps\API\Atlantis\Resources\Products\PriceResource;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\Product;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product as ProductModel;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Enums\CancellationActionPerformedType;
use Waterfront\Domain\Subscriptions\Enums\CancellationOfferType;
use Waterfront\Domain\Subscriptions\Enums\CancellationStepType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\CancellationFlow;
use Waterfront\Domain\Subscriptions\Models\CancellationFlowStep;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\CancellationFlowRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CancellationFlowService
{
    private const float DISCOUNT_PERCENTAGE_OFFER = 0.5;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly TranslatorInterface $translator,
        private readonly AuthenticationManager $authenticationManager,
        private readonly HostingService $hostingService,
        private readonly ExtendContractAction $extendContractAction,
        private readonly SubscriptionMutationRepository $subscriptionMutationRepository,
        private readonly LoggerInterface $logger,
        private readonly CancellationFlowRepository $cancellationFlowRepository,
        private readonly CancellationService $cancellationService,
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function start(Collection $subscriptions, string $ip): CancellationFlow
    {
        $subject = $this->authenticationManager->getAuthenticatedSubject();

        $cancellationFlow = new CancellationFlow();
        $cancellationFlow->identity_uuid = $subject->identitySchema->id->toString();
        $cancellationFlow->identity_metadata = json_encode([
            'email' => $subject->identitySchema->traits?->email,
            'schemaId' => $subject->identitySchema->schemaId,
        ], JSON_THROW_ON_ERROR);
        $cancellationFlow->ip_address = $ip;
        $cancellationFlow->save();
        $cancellationFlow->subscriptions()->attach($subscriptions->pluck('id')->toArray());

        return $cancellationFlow;
    }

    public function processCancellationSteps(
        CancellationFlow $cancellationFlow,
        CancellationStepType $stepType,
        string $stepData,
        string $responseData,
        CancellationActionPerformedType $action,
    ): void {
        $step = $this->storeCancellationStep($cancellationFlow->id, $stepType, $stepData, $responseData);

        if ($action === CancellationActionPerformedType::ABANDONED) {
            return;
        }

        match ($stepType) {
            CancellationStepType::CONFIRM_CANCELLATION => $this->confirmCancellation($cancellationFlow),
            CancellationStepType::CONFIRM_MUTATION => $this->confirmMutation($step),
            CancellationStepType::CONFIRM_PHONE => $this->confirmPhoneRequest($cancellationFlow),
            CancellationStepType::START,
            CancellationStepType::REASONS,
            CancellationStepType::SUPPORT,
            CancellationStepType::MUTATION_SUGGESTION,
            CancellationStepType::VALUE_LOSS_PREVENTION,
            CancellationStepType::FEEDBACK,
                => null,
        };
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return list<array<string, mixed>>
     */
    public function getOffers(Collection $subscriptions): array
    {
        return array_merge(
            $this->getPercentageDiscountOffers($subscriptions, (int) (self::DISCOUNT_PERCENTAGE_OFFER * 100)),
            $this->getSupportOffers(),
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     * @param int[]                         $contractPeriods
     * @param int[]                         $billingPeriods
     *
     * @return array<string, ResourceCollection>
     */
    public function getSubscriptionPrices(
        Collection $subscriptions,
        array $contractPeriods,
        array $billingPeriods,
    ): array {
        $products = $subscriptions->map(fn (Subscription $subscription) => $subscription->product)->unique();
        $productPriceRequests = array_map(fn ($product) => new RegistrationPriceRequest($product), $products->all());
        $pricelist = $this->priceResolver->getPriceList(
            new PriceRequest($productPriceRequests, $subscriptions->firstOrFail()->customer),
        );

        $subscriptionsWithPrices = [];
        foreach ($subscriptions as $subscription) {
            /** @var SupportCollection<int,Price> $productPrices */
            $productPrices = $pricelist
                ->filter(fn (Product $product) => $product->slug === $subscription->product->slug)
                ->firstOrFail()->prices;

            /** @var Price[] $filteredPrices */
            $filteredPrices = array_values(
                $productPrices
                    ->filter(
                        fn (Price $price) => (
                            in_array($price->contractPeriod, $contractPeriods, true)
                            && in_array($price->billingPeriod, $billingPeriods, true)
                        ),
                    )
                    ->filter(fn (Price $price) => $price->type === ProductPriceType::PROLONGATION)
                    ->filter(fn (Price $price) => $price->orderable)
                    ->toArray(),
            );

            $priceResources = PriceResource::collection($filteredPrices);
            $subscriptionsWithPrices[$subscription->uuid] = $priceResources;
        }

        return $subscriptionsWithPrices;
    }

    /**
     * @return list<array<string, string>>
     */
    public function getSupportOffers(): array
    {
        return [
            [
                'type' => CancellationOfferType::PHONE->value,
                'title' => $this->translator->translate('intelligent-cancellation.offers.phone.title'),
                'phonenumber' => $this->configuration->getAsString('bu.phone_number'),
            ],
        ];
    }

    /**
     * Creates offers for renewing the subscriptions for a year (billed once)
     * with a percentage discount on the price. Subscriptions that have no 12/12
     * prolongation price are excluded from the offer.
     *
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return list<array<string, mixed>>
     */
    public function getPercentageDiscountOffers(Collection $subscriptions, int $percentage): array
    {
        /** @var ProlongationPriceRequest[] $allProducts */
        $allProducts = $subscriptions->map(fn (Subscription $subscription) => new ProlongationPriceRequest($subscription->product))->toArray();
        $productPricelist = $this->priceResolver->getPriceList(
            new PriceRequest($allProducts, $subscriptions->firstOrFail()->customer),
        );

        $offer = [
            'type' => CancellationOfferType::PERCENTAGE_DISCOUNT->value,
            'title' => $this->translator->translate('intelligent-cancellation.offer.percentage-off', [
                'percentage' => $percentage . '%',
            ]),
            'recommended' => true,
            'contractPeriod' => 12,
            'percentageDiscount' => $percentage,
            'totalDiscountAmountNextInvoice' => 0,
            'subscriptions' => [],
        ];

        foreach ($subscriptions as $subscription) {
            $product = $productPricelist
                ->where(fn (Product $product) => $product->slug === $subscription->product->slug)
                ->firstOrFail();
            $productPrices = $product->prices;

            $yearlyProlongationPrice = $productPrices
                ->filter(fn (Price $price) => $price->type === ProductPriceType::PROLONGATION)
                ->filter(fn (Price $price) => $price->contractPeriod === 12 && $price->billingPeriod === 12)
                ->first();

            if ($yearlyProlongationPrice === null || $yearlyProlongationPrice->calculatedPrice === null) {
                continue;
            }

            $offer['totalDiscountAmountNextInvoice'] += (int) round(
                $yearlyProlongationPrice->calculatedPrice * ($percentage / 100),
            );
            $offer['subscriptions'][] = [
                'subscriptionUuid' => $subscription->uuid,
                'periodType' => 'all-at-once',
                'description' => $subscription->product->productGroup->slug === ProductGroupType::EXTENSION
                    ? $subscription->domain
                    : $product->name,
                'renewalDate' => $subscription->end_date,
            ];
        }

        if (count($offer['subscriptions']) === 0) {
            return [];
        }

        return [$offer];
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return list<array<string, int|string|bool>>
     */
    public function getStatistics(Collection $subscriptions): array
    {
        $statistics = [];

        foreach ($subscriptions as $subscription) {
            $statistics[] = match ($subscription->product->productGroup->slug) {
                ProductGroupType::EXTENSION => $this->getDomainStatistics($subscription),
                ProductGroupType::HOSTING => $this->getHostingStatistics($subscription),
                // For all other product groups we don't (yet) show statistics in the cancellation flow.
                ProductGroupType::ADD_ON,
                ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
                ProductGroupType::CLOUDSTACK_OS,
                ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
                ProductGroupType::CLOUDSTACK_VOLUME,
                ProductGroupType::DNS,
                ProductGroupType::DOMAIN_EXPANSION,
                ProductGroupType::MANUAL_SUBSCRIPTION,
                ProductGroupType::MICROSOFT_365,
                ProductGroupType::ONE_TIME_SERVICE,
                ProductGroupType::REDIRECT,
                ProductGroupType::RESELLER_DISCOUNT,
                ProductGroupType::RESELLER_HOSTING,
                ProductGroupType::OTHER,
                ProductGroupType::SSL,
                ProductGroupType::VOLUME_DISCOUNT,
                ProductGroupType::BACKUP,
                ProductGroupType::VPS,
                    => null,
            };
        }

        return array_values(array_filter($statistics, fn ($x) => $x !== null));
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function getReasons(): array
    {
        $reasonKeys = [1, 3, 4, 5, 6];
        shuffle($reasonKeys);

        $reasons = [];
        foreach ($reasonKeys as $reasonKey) {
            $reasons[] = [
                'id' => $reasonKey,
                'reason' => $this->translator->translate(sprintf('intelligent-cancellation.reason-%d', $reasonKey)),
            ];
        }

        // "Different reason" always at the bottom
        $reasons[] = ['id' => 7, 'reason' => $this->translator->translate('intelligent-cancellation.reason-7')];

        return $reasons;
    }

    public function validateStepData(Request $request, CancellationStepType $stepType): void
    {
        match ($stepType) {
            CancellationStepType::CONFIRM_CANCELLATION => $this->validateCancelConfirmationRequestStep($request),
            CancellationStepType::CONFIRM_MUTATION => $this->validateMutationConfirmationRequestStep($request),
            CancellationStepType::CONFIRM_PHONE,
            CancellationStepType::REASONS,
            CancellationStepType::SUPPORT,
            CancellationStepType::START,
            CancellationStepType::MUTATION_SUGGESTION,
            CancellationStepType::VALUE_LOSS_PREVENTION,
            CancellationStepType::FEEDBACK,
                => $this->validateGenericRequestStep($request),
        };
    }

    public function getCancellationFlowReason(Subscription $subscription): ?string
    {
        $step = $this->cancellationFlowRepository->findCancellationFlowStepBySubscriptionId($subscription->id);

        if (null === $step) {
            return null;
        }

        try {
            $data = json_decode($step->response_data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (is_array($data) && array_key_exists('reasons', $data) && is_array($data['reasons'])) {
            return implode(', ', $data['reasons']);
        }

        return null;
    }

    private function validateCancelConfirmationRequestStep(Request $request): void
    {
        $this->validateGenericRequestStep($request);
        $request->validate([
            'responseData' => ['required', 'array'],
            'responseData.action' => ['required', Rule::in(CancellationActionPerformedType::cases())],
            'responseData.data.confirmed' => ['exclude_if:responseData.action,abandoned', 'accepted'],
        ]);
    }

    private function validateMutationConfirmationRequestStep(Request $request): void
    {
        $this->validateGenericRequestStep($request);

        $request->validate([
            'responseData' => ['required', 'array'],
            'responseData.data.confirmed' => ['exclude_if:responseData.action,abandoned', 'accepted'],
            'responseData.data.offer' => ['required'],
            'responseData.data.offer.subscriptions' => ['required', 'array'],
            'responseData.data.offer.subscriptions.*.subscriptionUuid' => ['required', 'string'],
        ]);
    }

    private function validateGenericRequestStep(Request $request): void
    {
        $request->validate([
            'stepType' => ['required', Rule::in(CancellationStepType::cases())],
            'stepData' => ['required'],
            'responseData' => ['nullable'],
        ]);

        if (
            $request->input('responseData') !== null
            && ! json_validate(json_encode($request->input('responseData'), JSON_THROW_ON_ERROR))
        ) {
            throw ValidationException::withMessages(['responseData' => 'No valid json supplied']);
        }
    }

    private function confirmPhoneRequest(CancellationFlow $cancellationFlow): void
    {
        $this->finishCancellationFlow($cancellationFlow);
    }

    private function confirmCancellation(CancellationFlow $cancellationFlow): void
    {
        $subscriptions = $cancellationFlow->subscriptions()->get();
        foreach ($subscriptions as $subscription) {
            $this->cancellationService->cancel(
                $subscription,
                SubscriptionCancelType::CANCEL_END_DATE,
                SubscriptionCancelReason::REASON_CANCELLATION,
                false,
            );
        }

        $this->finishCancellationFlow($cancellationFlow);
    }

    private function confirmMutation(CancellationFlowStep $step): void
    {
        $cancellationFlow = $step->cancellationFlow;
        $subscriptions = $cancellationFlow->subscriptions()->get();

        $responseData = json_decode($step->response_data);
        assert($responseData instanceof stdClass);
        assert(property_exists($responseData, 'data'));
        $data = $responseData->data;
        assert(property_exists($data, 'offer'));
        $offer = $data->offer;
        assert(property_exists($offer, 'subscriptions'));

        $pricelist = $this->getPricelist($subscriptions);

        Assert::propertyExists($offer, 'contractPeriod');
        $contractPeriod = $offer->contractPeriod;
        Assert::positiveInteger($contractPeriod);

        foreach ($subscriptions as $subscription) {
            foreach ($responseData->data->offer->subscriptions as $offerSub) {
                if ($offerSub->subscriptionUuid !== $subscription->uuid) {
                    continue;
                }

                Assert::propertyExists($offerSub, 'subscriptionUuid');
                Assert::propertyExists($offerSub, 'periodType');

                $billingPeriod = match ($offerSub->periodType) {
                    'month' => 1,
                    'year' => 12,
                    'all-at-once' => $contractPeriod,
                    default => throw new InvalidArgumentException(sprintf(
                        'Provided period type is not a valid billing period: %s',
                        $offerSub->periodType,
                    )),
                };

                $price = $pricelist->getProductPrice($subscription->product->slug, $contractPeriod, $billingPeriod);
                Assert::natural($price->calculatedPrice);

                $offerPrice = (int) round($price->calculatedPrice * self::DISCOUNT_PERCENTAGE_OFFER);
                Assert::natural($offerPrice);

                $mutation = $this->subscriptionMutationRepository->findOpenMutation($subscription);
                if ($mutation !== null) {
                    continue;
                }

                $this->extendContractAction->execute($subscription, $billingPeriod, $contractPeriod, $offerPrice, null);
            }
        }

        $this->finishCancellationFlow($cancellationFlow);
    }

    /**
     * @param SupportCollection<int, Subscription> $subscriptions
     */
    private function getPricelist(SupportCollection $subscriptions): PriceList
    {
        /** @var Collection<int, ProductModel> $products */
        $products = $subscriptions->pluck('product');
        $productPriceRequests = array_map(fn ($product) => new ProlongationPriceRequest($product), $products->all());

        return $this->priceResolver->getPriceList(
            new PriceRequest($productPriceRequests, $subscriptions->firstOrFail()->customer),
        );
    }

    private function storeCancellationStep(
        int $flowId,
        CancellationStepType $stepType,
        string $stepData,
        string $responseData,
    ): CancellationFlowStep {
        $step = new CancellationFlowStep();
        $step->cancellation_flow_id = $flowId;
        $step->type = $stepType;
        $step->step_data = $stepData;
        $step->response_data = $responseData;
        $step->completed_at = CarbonImmutable::now();
        $step->save();

        return $step;
    }

    private function finishCancellationFlow(CancellationFlow $cancellationFlow): void
    {
        $cancellationFlow->completed_at = CarbonImmutable::now();
        $cancellationFlow->save();
    }

    /**
     * @return ?array<string, int|string>
     */
    private function getDomainStatistics(Subscription $subscription): ?array
    {
        if ($subscription->domain === null) {
            return null;
        }

        $stack = HandlerStack::create();
        $stack->setHandler(new CurlHandler());
        $stack->push(GuzzleTracingMiddleware::trace());
        $client = new GuzzleClient(['handler' => $stack]);
        $clientUrl = $this->configuration->getAsString('intelligent-cancellation.client_url');
        $bearerToken = $this->configuration->getAsString('intelligent-cancellation.client_bearer_token');
        $environment = Environment::from($this->configuration->getAsString('app.tenant_env'));

        // We only have an account for production use.
        if ($environment === Environment::TST || $environment === Environment::DEV) {
            return [
                'subscriptionUuid' => $subscription->uuid,
                'domain' => $subscription->domain,
                'domainValue' => 50000,
            ];
        }

        try {
            $response = $client->request(
                'GET',
                "{$clientUrl}/hosting/domain/{$subscription->domain}/domainvalue",
                [
                    'headers' => ['Authorization' => "Bearer {$bearerToken}"],
                    'connect_timeout' => 5,
                    'timeout' => 5,
                ],
            );
            $response = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            assert(is_array($response));
        } catch (GuzzleException|JsonException) {
            return null;
        }

        if (
            ! array_key_exists('attributes', $response)
            || ! is_array($response['attributes'])
            || ! array_key_exists('value_rounded', $response['attributes'])
            || ! is_numeric($response['attributes']['value_rounded'])
        ) {
            return null;
        }

        return [
            'subscriptionUuid' => $subscription->uuid,
            'domain' => $subscription->domain,
            'domainValue' => (int) $response['attributes']['value_rounded'] * 100,
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function getHostingStatistics(Subscription $subscription): array
    {
        if (! $subscription->hostingDeployment?->provider instanceof Provider || $subscription->domain === null) {
            return ['hostingStatisticsUnavailable' => true];
        }

        try {
            $statistics = $this->hostingService->getUserStats(
                $subscription,
                $subscription->hostingDeployment->provider->slug,
            );

            // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ]);

            // This shouldn't blow up for any reason. Showing brokeness to a customer wishing to cancel is bad.
            return ['hostingStatisticsUnavailable' => true];
        }

        if ($statistics === null || $statistics->mailDiskSpaceInMb === null) {
            return ['hostingStatisticsUnavailable' => true];
        }

        return [
            'hostingStatisticsUnavailable' => false,
            'subscriptionUuid' => $subscription->uuid,
            'usedStorage' => $statistics->diskSpaceInMb,
            'usedEmailStorage' => $statistics->mailDiskSpaceInMb,
            'domain' => $subscription->domain,
        ];
    }
}
