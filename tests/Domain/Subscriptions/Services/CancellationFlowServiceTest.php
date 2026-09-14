<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Enums\CancellationActionPerformedType;
use Waterfront\Domain\Subscriptions\Enums\CancellationOfferType;
use Waterfront\Domain\Subscriptions\Enums\CancellationStepType;
use Waterfront\Domain\Subscriptions\Models\CancellationFlow;
use Waterfront\Domain\Subscriptions\Models\CancellationFlowStep;
use Waterfront\Domain\Subscriptions\Models\CancellationFlowSubscriptions;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\CancellationFlowRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationFlowService;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(CancellationFlowService::class)]
class CancellationFlowServiceTest extends IntegrationTestCase
{
    private CancellationFlowService $cancellationFlowService;

    public function setUp(): void
    {
        parent::setUp();

        $this->cancellationFlowService = self::resolve(CancellationFlowService::class);
    }

    #[Test]
    public function flowCreation(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $domainSubscription = new SubscriptionFactory()
            ->for($product)
            ->for(new CustomerFactory())
            ->createOne();

        $flow = $this->cancellationFlowService->start(new Collection([$domainSubscription]), '127.0.0.1');

        self::assertDatabaseHas(CancellationFlow::class, ['id' => $flow->id]);
        self::assertDatabaseHas(CancellationFlowSubscriptions::class, [
            'cancellation_flows_id' => $flow->id,
            'subscription_id' => $domainSubscription->id,
        ]);
    }

    #[Test]
    public function subscriptionStatistics2(): void
    {
        $customer = new CustomerFactory()->createOne();
        $domainProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $domainSubscription = new SubscriptionFactory()
            ->for($domainProduct)
            ->for($customer)
            ->createOne(['domain' => 'example.org']);

        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        $hostingProvider = new ProviderFactory()->hostingDirectAdmin()->createOne();
        $hostingDeployment = new HostingDeploymentFactory()->for($hostingProvider)->for(new ServerFactory());
        $hostingSubscription = new SubscriptionFactory()
            ->for($hostingProduct)
            ->has($hostingDeployment)
            ->for($customer)
            ->createOne(['domain' => 'example.org']);

        $stats = $this->cancellationFlowService->getStatistics(new Collection([
            $hostingSubscription,
            $domainSubscription,
        ]));

        self::assertContains(
            [
                'subscriptionUuid' => $domainSubscription->uuid,
                'domain' => $domainSubscription->domain,
                'domainValue' => 50000,
            ],
            $stats,
        );

        self::assertContains(
            [
                'hostingStatisticsUnavailable' => false,
                'subscriptionUuid' => $hostingSubscription->uuid,
                'usedStorage' => 0,
                'usedEmailStorage' => 0,
                'domain' => 'example.org',
            ],
            $stats,
        );
    }

    #[Test]
    public function cancellationReasons(): void
    {
        $reasons = $this->cancellationFlowService->getReasons();

        // There should be exactly 6 reasons
        self::assertCount(6, $reasons);

        // Expected mapping of id to reason string
        $expectedReasons = [
            1 => 'intelligent-cancellation.reason-1',
            3 => 'intelligent-cancellation.reason-3',
            4 => 'intelligent-cancellation.reason-4',
            5 => 'intelligent-cancellation.reason-5',
            6 => 'intelligent-cancellation.reason-6',
            7 => 'intelligent-cancellation.reason-7',
        ];

        // The last reason should always be id 7 and have the correct reason string
        self::assertSame(7, $reasons[5]['id']);
        self::assertSame($expectedReasons[7], $reasons[5]['reason']);

        // The first 5 reasons should be unique, not contain id 7, and be valid, with correct reason strings
        $validIds = [1, 3, 4, 5, 6];
        $firstFive = array_slice($reasons, 0, 5);
        $firstFiveIds = array_map(static fn ($r) => $r['id'], $firstFive);
        self::assertCount(5, array_unique($firstFiveIds));
        foreach ($firstFive as $reason) {
            self::assertContains($reason['id'], $validIds);
            self::assertSame($expectedReasons[$reason['id']], $reason['reason']);
        }

        self::assertNotContains(7, $firstFiveIds);
    }

    #[Test]
    public function supportOffers(): void
    {
        $offer = $this->cancellationFlowService->getSupportOffers();

        self::assertSame(
            [
                [
                    'type' => CancellationOfferType::PHONE->value,
                    'title' => 'intelligent-cancellation.offers.phone.title',
                    'phonenumber' => '+311234567',
                ],
            ],
            $offer,
        );
    }

    #[Test]
    public function percentageDiscountOffers(): void
    {
        $domain = Faker::create()->domainName();
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->forDomain($domain)
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 100_00,
            ]);

        $offers = $this->cancellationFlowService->getPercentageDiscountOffers(
            new Collection([$subscription]),
            50,
        );

        self::assertCount(1, $offers);

        $offer = $offers[0];
        self::assertSame(CancellationOfferType::PERCENTAGE_DISCOUNT->value, $offer['type']);
        self::assertTrue($offer['recommended']);
        self::assertSame(12, $offer['contractPeriod']);
        self::assertSame(50, $offer['percentageDiscount']);
        self::assertSame(50_00, $offer['totalDiscountAmountNextInvoice']);
        /** @var list<array<string, mixed>> $subscriptions */
        $subscriptions = $offer['subscriptions'];
        self::assertCount(1, $subscriptions);
        self::assertSame($subscription->uuid, $subscriptions[0]['subscriptionUuid']);
        self::assertSame('all-at-once', $subscriptions[0]['periodType']);
        self::assertSame($subscription->domain, $subscriptions[0]['description']);
        self::assertEquals($subscription->end_date, $subscriptions[0]['renewalDate']);
    }

    #[Test]
    public function percentageDiscountOffersSkipsSubscriptionWithoutYearlyPrice(): void
    {
        $domain = Faker::create()->domainName();
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->forDomain($domain)
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'contract_period' => 1,
                'billing_period' => 1,
            ]);

        $offers = $this->cancellationFlowService->getPercentageDiscountOffers(
            new Collection([$subscription]),
            50,
        );

        self::assertCount(0, $offers);
    }

    #[Test]
    public function percentageDiscountOffersMultipleSubscriptionsAccumulatesDiscount(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();

        $subscriptionA = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for($productGroup)->createOne())
            ->forDomain('subscription-a.nl')
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($subscriptionA->product)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 100_00,
            ]);

        $subscriptionB = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for($productGroup)->createOne())
            ->forDomain('subscription-b.nl')
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($subscriptionB->product)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 200_00,
            ]);

        $subscriptionWithoutYearlyPrice = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for($productGroup)->createOne())
            ->forDomain('subscription-without-yearly-price.nl')
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($subscriptionWithoutYearlyPrice->product)
            ->prolongation()
            ->createOne([
                'contract_period' => 1,
                'billing_period' => 1,
                'price' => 100_00,
            ]);

        $offers = $this->cancellationFlowService->getPercentageDiscountOffers(
            new Collection([$subscriptionA, $subscriptionB, $subscriptionWithoutYearlyPrice]),
            50,
        );

        self::assertCount(1, $offers);
        self::assertSame(150_00, $offers[0]['totalDiscountAmountNextInvoice']);
        /** @var list<array<string, mixed>> $subscriptions */
        $subscriptions = $offers[0]['subscriptions'];
        self::assertCount(2, $subscriptions);
        self::assertSame($subscriptionA->uuid, $subscriptions[0]['subscriptionUuid']);
        self::assertSame($subscriptionB->uuid, $subscriptions[1]['subscriptionUuid']);
    }

    #[test]
    public function triggerCancelFlowWhenStepTypeIsConfirmCancellation(): void
    {
        $sub = DomainSubscriptionDataProvider::subscription();

        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::once())->method('cancel');
        $this->app->bind(CancellationService::class, static fn () => $cancellationService);

        $cancellationFLow = $this->createCancellationFlow($sub);

        self::resolve(CancellationFlowService::class)
            ->processCancellationSteps(
                $cancellationFLow,
                CancellationStepType::CONFIRM_CANCELLATION,
                '{}',
                '{}',
                CancellationActionPerformedType::CONTINUE,
            );
        self::assertDatabaseHas(CancellationFlow::class, [
            'id' => $cancellationFLow->id,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    #[test]
    public function confirmMutationDoNotCreateExtraMutation(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['billing_period' => 1, 'contract_period' => 36]);
        new SubscriptionMutationFactory()->for($subscription)->createOne(['product_id' => $subscription->product->id]);

        $mutationActionMock = self::createMock(ExtendContractAction::class);
        $mutationActionMock->expects(self::never())->method('execute');
        $this->app->bind(ExtendContractAction::class, static fn () => $mutationActionMock);

        $responseData = [
            'data' => [
                'offer' => [
                    'subscriptions' => [
                        0 => [
                            'subscriptionUuid' => $subscription->uuid,
                            'periodType' => 'month',
                        ],
                    ],
                    'contractPeriod' => 36,
                ],
            ],
        ];

        $cancellationFLow = $this->createCancellationFlow($subscription);

        $jsonData = json_encode($responseData, JSON_THROW_ON_ERROR);
        self::resolve(CancellationFlowService::class)
            ->processCancellationSteps(
                $cancellationFLow,
                CancellationStepType::CONFIRM_MUTATION,
                '{}',
                $jsonData,
                CancellationActionPerformedType::CONTINUE,
            );
        self::assertDatabaseHas(CancellationFlow::class, [
            'id' => $cancellationFLow->id,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    #[test]
    public function confirmMutation(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['billing_period' => 1, 'contract_period' => 36]);

        $mutationActionMock = self::createMock(ExtendContractAction::class);
        $mutationActionMock->expects(self::once())->method('execute');
        $this->app->bind(ExtendContractAction::class, static fn () => $mutationActionMock);

        $responseData = [
            'data' => [
                'offer' => [
                    'subscriptions' => [
                        0 => [
                            'subscriptionUuid' => $subscription->uuid,
                            'periodType' => 'month',
                        ],
                    ],
                    'contractPeriod' => 36,
                ],
            ],
        ];

        $cancellationFLow = $this->createCancellationFlow($subscription);

        $jsonData = json_encode($responseData, JSON_THROW_ON_ERROR);
        self::resolve(CancellationFlowService::class)
            ->processCancellationSteps(
                $cancellationFLow,
                CancellationStepType::CONFIRM_MUTATION,
                '{}',
                $jsonData,
                CancellationActionPerformedType::CONTINUE,
            );
        self::assertDatabaseHas(CancellationFlow::class, [
            'id' => $cancellationFLow->id,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    #[test]
    public function saveStepsWithNoAction(): void
    {
        $sub = DomainSubscriptionDataProvider::subscription();

        $cancellationFLow = $this->createCancellationFlow($sub);

        self::resolve(CancellationFlowService::class)
            ->processCancellationSteps(
                $cancellationFLow,
                CancellationStepType::REASONS,
                '{}',
                '{}',
                CancellationActionPerformedType::CONTINUE,
            );
        self::assertDatabaseHas(CancellationFlowStep::class, [
            'cancellation_flow_id' => $cancellationFLow->id,
            'type' => CancellationStepType::REASONS,
        ]);
    }

    #[Test]
    public function returnsCommaSeparatedReasons(): void
    {
        $repository = $this->createStub(CancellationFlowRepository::class);
        $cancellationFlowService = new CancellationFlowService(
            self::resolve(ConfigurationInterface::class),
            self::resolve(TranslatorInterface::class),
            self::resolve(AuthenticationManager::class),
            self::resolve(HostingService::class),
            self::resolve(ExtendContractAction::class),
            self::resolve(SubscriptionMutationRepository::class),
            self::resolve(LoggerInterface::class),
            $repository,
            self::resolve(CancellationService::class),
            self::resolve(PriceResolver::class),
        );

        $sub = DomainSubscriptionDataProvider::subscription();

        $reasons = ['Too expensive', 'Not needed anymore'];
        $step = new CancellationFlowStep();
        $reasonJSON = json_encode(['reasons' => $reasons]);
        self::assertNotFalse($reasonJSON);
        $step->response_data = $reasonJSON;

        $repository->method('findCancellationFlowStepBySubscriptionId')->willReturn($step);

        $result = $cancellationFlowService->getCancellationFlowReason($sub);

        self::assertSame('Too expensive, Not needed anymore', $result);
    }

    #[Test]
    public function returnSubscriptionPrices(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['contract_period' => 36, 'billing_period' => 12]);
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['contract_period' => 36, 'billing_period' => 1]);
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['contract_period' => 24, 'billing_period' => 12]);
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['contract_period' => 24, 'billing_period' => 1]);
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne(['contract_period' => 1, 'billing_period' => 1, 'orderable' => false]);

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->registration()
            ->createOne(['contract_period' => 12, 'billing_period' => 1]);

        $subscriptionPrices = self::resolve(CancellationFlowService::class)
            ->getSubscriptionPrices(Subscription::all(), [36, 24], [12, 1]);

        self::assertCount(1, $subscriptionPrices);
        self::assertArrayHasKey($subscription->uuid, $subscriptionPrices);
        self::assertCount(4, $subscriptionPrices[$subscription->uuid]);
    }

    private function createCancellationFlow(Subscription $subscription): CancellationFlow
    {
        $cancellationFLow = new CancellationFlow();
        $cancellationFLow->identity_uuid = Uuid::uuid4()->toString();
        $cancellationFLow->identity_metadata = '{}';
        $cancellationFLow->ip_address = '127.0.0.1';
        $cancellationFLow->save();
        $cancellationFLow->subscriptions()->attach($subscription);

        return $cancellationFLow;
    }
}
