<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSuspendSubscriptionAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(NovaSuspendSubscriptionAction::class)]
class SuspendSubscriptionTest extends IntegrationTestCase
{
    private NovaSuspendSubscriptionAction $suspendSubscriptionAction;

    private Customer $customer;

    private Subscription $domainSubscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->actingAsEmployee();

        $this->suspendSubscriptionAction = self::resolve(NovaSuspendSubscriptionAction::class);

        $this->customer = new CustomerFactory()->createOne();

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionProductGroup)->createOne();

        $this->domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'administrative_status' => AdministrativeStatus::ACTIVE->value,
                    'technical_status' => TechnicalStatus::OK->value,
                ]
            );
    }

    #[Test]
    public function suspendDomainSubscriptionSuccessful(): void
    {
        $this->mockRtrClient();

        new DomainDeploymentFactory()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->domainSubscription->uuid,
        ]);

        $this->suspendSubscriptionAction->handle(
            new ActionFields(new Collection(), new Collection()),
            new Collection([$this->domainSubscription]),
        );

        $this->customer->refresh();
        $this->domainSubscription->refresh();

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->domainSubscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->domainSubscription->technical_status);
    }

    #[Test]
    public function suspendSubscriptionFailedNotImplementedByDomainProvider(): void
    {
        new DomainDeploymentFactory()->withPlaceholderProvider()->createOne([
            'subscription_uuid' => $this->domainSubscription->uuid,
        ]);

        $this->suspendSubscriptionAction->handle(
            new ActionFields(new Collection(), new Collection()),
            new Collection([$this->domainSubscription]),
        );

        $this->customer->refresh();
        $this->domainSubscription->refresh();

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->domainSubscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENSION_FAILED->value, $this->domainSubscription->technical_status);
    }

    public function mockRtrClient(): void
    {
        $rtrRequests = [];

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                200,
                [],
                json_encode($this->getMockedResult(), JSON_THROW_ON_ERROR)
            ),
            new Response(
                200,
                []
            ),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $rtrService = self::resolve(RtrService::class);
        $rtrService->setClient($sdk);

        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->singleton(RtrService::class, fn () => $rtrService);
    }

    /**
     * @return array<string, mixed>
     */
    private function getMockedResult(): array
    {
        return [
            'domainName' => $this->domainSubscription->domain,
            'registry' => 'sidn',
            'customer' => 'johndoe',
            'registrant' => 'johndoe',
            'privacyProtect' => true,
            'status' => ['PENDING_RENEW'],
            'authcode' => '294759302',
            'languageCode' => 'nl',
            'autoRenew' => true,
            'autoRenewPeriod' => 12,
            'ns' => [
                'ns1.nl',
            ],
            'childHosts' => [
                'example.com',
            ],
            'createdDate' => '2020-08-30 01:02:03',
            'updatedDate' => '2020-08-30 01:02:03',
            'expiryDate' => '2020-11-30 01:02:03',
            'premium' => false,
            'zone' => [
                'id' => 1334,
                'template' => 'template-01',
                'link' => true,
                'dnssec' => true,
                'service' => 'BASIC',
            ],
            'contacts' => [
                [
                    'role' => 'ADMIN',
                    'handle' => 'johndoe',
                ],
                [
                    'role' => 'ADMIN',
                    'handle' => 'johndoe',
                ],
            ],
            'keyData' => [
                [
                    'protocol' => 3,
                    'flags' => 256,
                    'algorithm' => 10,
                    'publicKey' => 'TFMwdFVsTkJMUzB0SUdGelpHWmhjMlJtWVhOa1ptRnpaR1poYzJSbQ==',
                ],
                [
                    'protocol' => 3,
                    'flags' => 257,
                    'algorithm' => 8,
                    'publicKey' => 'WmhjMlJtWVhOa1ptRnpaR1poYzJSbUxTMHRVbE5CTFMwdElHRnpaRw==',
                ],
            ],
            'ds_data' => [
                [
                    'keyTag' => 1,
                    'algorithm' => 5,
                    'digestType' => 2,
                    'digest' => 'blablablabla',
                ],
                [
                    'keyTag' => 1,
                    'algorithm' => 5,
                    'digestType' => 2,
                    'digest' => 'blablablabla',
                ],
            ],
        ];
    }
}
