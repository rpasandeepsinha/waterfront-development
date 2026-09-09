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
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaMicrosoft365RetryOrderModifyAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Microsoft365Service $microsoft365Service,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry-modify-kpn-orders');
    }

    /**
     * @param Collection<int, Microsoft365Deployment> $microsoft365Deployments
     */
    public function handle(ActionFields $fields, Collection $microsoft365Deployments): ActionResponse|static
    {
        foreach ($microsoft365Deployments as $microsoft365Deployment) {
            $microsoft365CustomerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

            if ($microsoft365CustomerInfo->mca_signed_at === null) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-mca-not-signed'));
            }

            $microsoft365ChildSubscriptionCount = $microsoft365Deployment
                ->subscriptionChildren
                ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                ->count();

            if ($microsoft365ChildSubscriptionCount === 0) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-no-seats'));
            }

            try {
                assert(is_string($microsoft365Deployment->microsoft365CustomerInfo->kpn_customer_id));
                $KpnCustomerId = str_replace('CID', '', $microsoft365Deployment->microsoft365CustomerInfo->kpn_customer_id);
                $childSubscription = $microsoft365Deployment->subscriptionChildren[0];
                Assert::isInstanceOf($childSubscription, Subscription::class);
                $microsoft365OrderSummary = $this->microsoft365Service->orderSummary(customer: (int) $KpnCustomerId, productName: $childSubscription->product->name);
            } catch (OrderSummaryException|OrderSummaryCustomerNotFoundException $e) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-order-summary-retrieval'));
            }

            try {
                $successful = $this->microsoft365Service->modifyOrder(orderId: (int) $microsoft365Deployment->kpn_order_id, amount: $microsoft365ChildSubscriptionCount - ($microsoft365OrderSummary[0]->getQuantity()));
            } catch (Office365Exception $e) {
                Log::error(sprintf(
                    'Error while modifying order for order_id: [%s] with amount: [%s]. With exception message: %s',
                    $microsoft365Deployment->kpn_order_id,
                    $microsoft365ChildSubscriptionCount - ($microsoft365OrderSummary[0]->getQuantity()),
                    $e->getMessage(),
                ));

                return self::danger($this->translator->translate('nova-action.failed.microsoft365-modify-failed'));
            }

            if (! $successful) {
                return self::danger($this->translator->translate('nova-action.failed.microsoft365-modify-failed'));
            }
        }

        return Action::message($this->translator->translate('nova-action.success.microsoft365-order-modified'));
    }
}
