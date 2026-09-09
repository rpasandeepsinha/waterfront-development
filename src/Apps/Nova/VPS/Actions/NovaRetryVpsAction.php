<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Exceptions\HelperNotSupported;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\MetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\OsMetaData;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaRetryVpsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly VpsService $vpsService,
        private readonly LoggerInterface $logger,
        private readonly VirtualMachineDeploymentRepositoryInterface $vmSubscriptionRepository,
        private readonly CartSerializerFactory $cartSerializerFactory,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
            $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::VPS)
        );

        $this->sole();
    }

    /**
     * @throws HelperNotSupported
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Heading::make("<h3 class=\"text-xl\">{$this->translator->translate('nova-action.retry_vps.strategy_header')}</h3><hr /><p class=\"text-md\">{$this->translator->translate('nova-action.retry_vps.strategy_p')}</p>")
                ->asHtml(),
            NovaBoolField::make(
                $this->translator->translate('nova-action.retry_vps.delete_vm_first'),
                'delete_vm_first'
            )->help($this->translator->translate('nova-action.retry_vps.delete_vm_first_help')),
        ];
    }

    /**
     * @param Collection<int, Subscription> $models
     *
     * @throws VirtualMachineNotFoundException
     **/
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $subscription = $models->first();
        Assert::isInstanceOf($subscription, Subscription::class, 'Only Base Subscriptions allowed');

        $deleteVmFirst = (bool) $fields['delete_vm_first'];

        $deployment = $subscription->cloudStackVirtualMachineDeployment()->first();

        if ($deployment instanceof VirtualMachineDeployment) {
            $sshKeyUuid = $deployment->sshKeys->isNotEmpty()
                ? $deployment->sshKeys->first()->uuid->toString()
                : null;
        } else {
            $sshKeyUuid = $this->getSshKeyUuidFromMetadata($subscription);
        }

        $context = [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
        ];

        if ($deployment instanceof VirtualMachineDeployment) {
            $context[LoggingContextKeys::PROVISIONING_ID] = $deployment->id;
        }

        $this->logger->debug(
            $deleteVmFirst
            ? 'Start Nova VPS Retry action with deleting existing VM deployment'
            : 'Start Nova VPS Retry action without deleting existing VM deployment',
            $context
        );

        try {
            $this->vpsService->retry(
                subscription: $subscription,
                sshKeyUuid: $sshKeyUuid,
                deleteVmFirst: $deleteVmFirst,
            );

            return ActionResponse::message($this->translator->translate('nova-action.retry_vps_success'));
        } catch (Throwable $e) { // @phpstan-ignore-line
            $this->logger->error('An error occurred while redeploying the VPS', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::CUSTOMER_ID => $subscription->customer->id,
                LoggingContextKeys::PRODUCT_UUID => $subscription->product->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::EXCEPTION => $e,
                LoggingContextKeys::META => [
                    'delete_vm_first' => $deleteVmFirst,
                    'ssh_key_uuid' => $sshKeyUuid,
                ],
            ]);

            return ActionResponse::danger($this->translator->translate('nova-action.retry_vps_failed'));
        }
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry_vps');
    }

    /**
     * @throws VirtualMachineNotFoundException
     */
    private function getSshKeyUuidFromMetadata(Subscription $subscription): ?string
    {
        $osSubscription = $this->vmSubscriptionRepository
            ->getOsSubscriptionChildFromSubscriptionUuid($subscription->uuid);

        $metaData = null;
        if ($osSubscription->orderLineItem?->meta_data !== null) {
            $osSubscriptionMetaData = $osSubscription->orderLineItem->meta_data;
            $metaData = $this->cartSerializerFactory->get()
                ->deserialize($osSubscriptionMetaData, MetaData::class, 'json');
        }

        return $metaData instanceof OsMetaData
            ? $metaData->sshKeyUuid
            : null;
    }
}
