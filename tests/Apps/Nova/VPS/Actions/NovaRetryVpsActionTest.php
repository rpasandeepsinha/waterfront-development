<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\VPS\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\VPS\Actions\NovaRetryVpsAction;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\VirtualMachineDeploymentRepository;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(NovaRetryVpsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaRetryVpsActionTest extends IntegrationTestCase
{
    private NovaRetryVpsAction $action;

    private Subscription $subscription;

    private Subscription $subscriptionAlreadyDeployed;

    private Subscription $subscriptionWithOutManagerDomainDeployment;

    private VpsService&MockObject $vpsServiceMock;

    private OrderLineItem $osOrderLineItem;

    private ManagerDomainDeployment $managerDomainDeployment;

    private LoggerInterface&MockObject $loggerMock;

    private SshKey $defaultSshKey;

    private SshKey $orderSshKey;

    private Environment $cloudstackEnvironment;

    public function setUp(): void
    {
        parent::setUp();

        /** @var TranslatorInterface&MockObject $translatorMock */
        $translatorMock = self::createMock(TranslatorInterface::class);

        $this->vpsServiceMock = self::createMock(VpsService::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);

        $this->action = new NovaRetryVpsAction(
            translator: $translatorMock,
            vpsService: $this->vpsServiceMock,
            logger: $this->loggerMock,
            vmSubscriptionRepository: self::resolve(VirtualMachineDeploymentRepository::class),
            cartSerializerFactory: self::resolve(CartSerializerFactory::class),
        );

        $productGroup = ProductGroupFactory::new()->cloudstackVirtualMachine()->createOne();
        $osProduct = ProductFactory::new()->ubuntu()->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->for(CustomerFactory::new())
            ->for(ProductFactory::new()->for($productGroup))
            ->createOne();

        SubscriptionFactory::new()
            ->for($this->subscription->customer)
            ->for($osProduct)
            ->parentSubscription($this->subscription)
            ->createOne();

        $this->subscriptionAlreadyDeployed = SubscriptionFactory::new()
            ->for(CustomerFactory::new())
            ->for(ProductFactory::new()->for($productGroup))
            ->createOne();

        $this->subscriptionWithOutManagerDomainDeployment = SubscriptionFactory::new()
            ->for(CustomerFactory::new())
            ->for(ProductFactory::new()->for($productGroup))
            ->createOne();

        $osSubscription = SubscriptionFactory::new()
            ->for($this->subscriptionWithOutManagerDomainDeployment->customer)
            ->for($osProduct)
            ->parentSubscription($this->subscriptionWithOutManagerDomainDeployment)
            ->createOne();

        $this->orderSshKey = SshKeyFactory::new()
            ->for($this->subscriptionWithOutManagerDomainDeployment->customer)
            ->createOne();

        $order = OrderFactory::new()
            ->for($this->subscriptionWithOutManagerDomainDeployment->customer)
            ->createOne();

        $metaContent = [
            'type' => ProductGroupType::CLOUDSTACK_OS->value,
            'sshKeyUuid' => $this->orderSshKey->uuid->toString(),
        ];

        $this->osOrderLineItem = OrderLineItemFactory::new()
            ->for($order)
            ->createOne(
                [
                    'product_uuid' => $osSubscription->product->uuid,
                    'subscription_uuid' => $osSubscription->uuid,
                    'meta_data' => json_encode($metaContent),
                ]
            );

        $this->defaultSshKey = SshKeyFactory::new()
            ->for($this->subscription->customer)
            ->createOne();

        $this->cloudstackEnvironment = CloudstackEnvironmentFactory::new()->createOne();
        $this->managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()
            ->for($this->subscription->customer)
            ->for($this->cloudstackEnvironment)
            ->createOne();
    }

    #[Test]
    public function vpsRetryActionFailDueIncorrectSubscriptionModel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Only Base Subscriptions allowed');

        $models = new Collection();
        $this->action->handle($this->getActionFields([]), $models);
    }

    #[Test]
    public function vpsRetryActionWithExistingVMDeploymentSuccess(): void
    {
        $virtualMachineDeployment = $this->createVirtualMachineDeployment();
        $virtualMachineDeployment->sshKeys()->save($this->defaultSshKey);

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                $this->defaultSshKey->uuid->toString(),
                true
            );

        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with('Start Nova VPS Retry action with deleting existing VM deployment', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_ID => $virtualMachineDeployment->id,
            ]);

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields(['delete_vm_first' => true]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionWithExistingVMDeploymentReinstallsWithoutDeleting(): void
    {
        $virtualMachineDeployment = $this->createVirtualMachineDeployment();
        $virtualMachineDeployment->sshKeys()->save($this->defaultSshKey);

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                $this->defaultSshKey->uuid->toString(),
                false
            );

        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with('Start Nova VPS Retry action without deleting existing VM deployment', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_ID => $virtualMachineDeployment->id,
            ]);

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields(['delete_vm_first' => false]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionWithExistingVMDeploymentSuccessWithoutSshKey(): void
    {
        $virtualMachineDeployment = $this->createVirtualMachineDeployment();

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                null,
                true
            );

        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with('Start Nova VPS Retry action with deleting existing VM deployment', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_ID => $virtualMachineDeployment->id,
            ]);

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields(['delete_vm_first' => true]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionWithExistingVMDeploymentFailed(): void
    {
        $virtualMachineDeployment = $this->createVirtualMachineDeployment();
        $virtualMachineDeployment->sshKeys()->save($this->defaultSshKey);

        $exception = new ClientException('Something went wrong');

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                $this->defaultSshKey->uuid->toString(),
                true
            )->willThrowException($exception);

        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with('Start Nova VPS Retry action with deleting existing VM deployment', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_ID => $virtualMachineDeployment->id,
            ]);

        $this->loggerMock->expects(self::once())
            ->method('error')
            ->with(
                'An error occurred while redeploying the VPS',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::CUSTOMER_ID => $this->subscription->customer->id,
                    LoggingContextKeys::PRODUCT_UUID => $this->subscription->product->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'delete_vm_first' => true,
                        'ssh_key_uuid' => $this->defaultSshKey->uuid->toString(),
                    ],
                ]
            );

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields(['delete_vm_first' => true]), $models);

        self::assertInstanceOf(Message::class, $response['danger']);
    }

    #[Test]
    public function vpsRetryActionWithoutExistingVMDeploymentSuccess(): void
    {
        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                null,
                false
            );

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields([]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionWithoutExistingVMDeploymentWithoutKeysSuccess(): void
    {
        $this->osOrderLineItem->meta_data = json_encode([
            'type' => ProductGroupType::CLOUDSTACK_OS->value,
            'sshKeyUuid' => null,
        ], JSON_THROW_ON_ERROR);

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                null,
                false
            );

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields([]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionWithoutManagerDomainSuccess(): void
    {
        $this->assertDatabaseMissing(
            'cloudstack_managerdomain_deployments',
            [
                'customer_id' => $this->subscriptionWithOutManagerDomainDeployment->customer->id,
                'environment_id' => $this->cloudstackEnvironment->id,
            ]
        );

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscriptionWithOutManagerDomainDeployment,
                $this->orderSshKey->uuid,
                false
            );

        $models = new Collection([$this->subscriptionWithOutManagerDomainDeployment]);
        $response = $this->action->handle($this->getActionFields([]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    #[Test]
    public function vpsRetryActionAlreadyHasVirtualMachinesOnManagerDomainDeployment(): void
    {
        new CloudstackVirtualMachineDeploymentFactory()
            ->for($this->subscriptionAlreadyDeployed)
            ->for($this->managerDomainDeployment)
            ->createOne();

        $this->vpsServiceMock->expects(self::once())
            ->method('retry')
            ->with(
                $this->subscription,
                null,
                false
            );

        $models = new Collection([$this->subscription]);
        $response = $this->action->handle($this->getActionFields([]), $models);

        self::assertInstanceOf(Message::class, $response['message']);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getActionFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }

    private function createVirtualMachineDeployment(): VirtualMachineDeployment
    {
        return CloudstackVirtualMachineDeploymentFactory::new()
            ->for($this->managerDomainDeployment)
            ->for($this->subscription)
            ->createOne();
    }
}
