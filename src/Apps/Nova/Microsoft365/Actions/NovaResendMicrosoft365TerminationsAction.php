<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaResendMicrosoft365TerminationsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Microsoft365Service $microsoft365Service,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::MICROSOFT_365)
                && $this->onlyForSingleCustomer($request)
        );
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.microsoft365_retry_termination');
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        /** @var Collection<int, Subscription> $cleanedSubscriptions */
        $cleanedSubscriptions = new Collection([]);

        // Loops over all subscriptions and pushes the parent to the collection.
        // Everything that is not administrative_status 'archiving' or not from the microsoft_365 group will be ignored.
        foreach ($subscriptions as $subscription) {
            $check = $this->checkMicrosoft365GroupAndStatus($subscription);
            if ($check === null) {
                continue;
            }
            if ($subscription->parent_subscription_id === null) {
                $cleanedSubscriptions->push($subscription);
            }
            assert($subscription->parent !== null);
            $cleanedSubscriptions->push($subscription->parent);
        }

        // Ensures we only have unique parent subscriptions
        $uniqueMicrosoft365Subscriptions = $cleanedSubscriptions->unique('uuid');

        foreach ($uniqueMicrosoft365Subscriptions as $subscription) {
            $microsoft365Deployment = $subscription->microsoft365Deployment;

            $archivingChildren = $subscription->children->filter(
                fn (Subscription $subscription) => $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
            );
            $archivingChildrenCount = $archivingChildren->count();

            if ($microsoft365Deployment === null) {
                Log::error(
                    sprintf(
                        'Subscription [%s] does not have a microsoft subscription.',
                        $subscription->id,
                    )
                );
                return Action::message($this->translator->translate('nova-action.failed.no-microsoft365-subscription'));
            }

            $microsoft365CustomerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

            if ($microsoft365CustomerInfo->mca_signed_at === null) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-mca-not-signed'));
            }

            try {
                // This ensures that the parent and all child subscriptions have administrative status 'archiving'.
                if (
                    $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                    && $archivingChildrenCount === $subscription->children->count()
                ) {
                    $this->microsoft365Service->terminateOrder($microsoft365Deployment);
                    continue;
                }
                $this->microsoft365Service->modifyOrder((int) $microsoft365Deployment->kpn_order_id, $archivingChildrenCount * -1);
            } catch (Office365Exception $e) {
                Log::error(
                    sprintf(
                        'Error while terminating order for KPN order_id [%s] with nova action. Exception message: %s',
                        $microsoft365Deployment->kpn_order_id,
                        $e->getMessage(),
                    )
                );
                return Action::message($this->translator->translate('nova-action.failed.microsoft365-termination-error'));
            }
        }

        return Action::message($this->translator->translate('nova-action.success.microsoft365-child-subscriptions-created'));
    }

    private function checkMicrosoft365GroupAndStatus(Subscription $subscription): ?Subscription
    {
        if (
            $subscription->product->productGroup->slug->value === ProductGroupType::MICROSOFT_365->value
            && $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
        ) {
            return $subscription;
        }
        return null;
    }
}
