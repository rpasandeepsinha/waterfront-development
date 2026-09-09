<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Helpers\Money;

abstract class NovaSubscriptionAction extends Action
{
    private const string CHAR_PARENT_CHILD = '&#x21B3;';
    private const string CHAR_PARENT_NEXT_CHILD = '&#x2192';

    /**
     * @var null|Collection<int, Subscription>
     */
    private static ?Collection $cachedRequestCollection = null;

    protected function onlyForSingleSubscription(NovaRequest $request): bool
    {
        return ! $request->allResourcesSelected()
            && $request->selectedResourceIds()?->count() === 1;
    }

    protected function onlyForSubscriptionsWithProductGroupType(NovaRequest $request, ProductGroupType $productGroupType): bool
    {
        $subscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        if ($subscriptions->isEmpty()) {
            return false;
        }

        foreach ($subscriptions as $model) {
            if ($model->product->productGroup->slug !== $productGroupType) {
                return false;
            }
        }

        return true;
    }

    protected function onlyForSingleCustomer(NovaRequest $request): bool
    {
        $subscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        $customerNumbers = $subscriptions->unique(
            fn (Subscription $subscription) => $subscription->customer_id
        )->pluck('customer_id');

        return $customerNumbers->count() === 1;
    }

    protected function onlyForSuspendedSubscriptions(NovaRequest $request): bool
    {
        $subscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        if ($subscriptions->isEmpty()) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if ($subscription->technical_status !== TechnicalStatus::SUSPENDED->value) {
                return false;
            }
        }

        return true;
    }

    protected function onlyForSuspendableSubscriptions(NovaRequest $request): bool
    {
        $subscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        if ($subscriptions->isEmpty()) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if (in_array(
                $subscription->technical_status,
                [
                    TechnicalStatus::SUSPENDED->value,
                    TechnicalStatus::SUSPENDING->value,
                    TechnicalStatus::UNSUSPENDING->value,
                    AdministrativeStatus::SUSPENDED->value,
                ],
                true
            )) {
                return false;
            }
        }

        return true;
    }

    protected function onlyForExpiredSubscriptions(NovaRequest $request): bool
    {
        $subscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        if ($subscriptions->isEmpty()) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if (! in_array(
                $subscription->administrative_status,
                [AdministrativeStatus::EXPIRED->value, AdministrativeStatus::INACTIVE->value],
                true
            )) {
                return false;
            }
        }
        return true;
    }

    /**
     * This prevents loading the selected Models multiple time from the request,
     * as each action will propably try to fetch them.
     * And it will also validate that the selected resources are actual subscription
     * Models.
     *
     * @return Collection<int, Subscription>
     */
    protected function getSelectedSubscriptionsFromRequest(NovaRequest $request): Collection
    {
        if (self::$cachedRequestCollection instanceof Collection) {
            return self::$cachedRequestCollection;
        }

        $selectedResources = $request->selectedResources();

        if (! $selectedResources instanceof Collection) {
            self::$cachedRequestCollection = new Collection();
            return self::$cachedRequestCollection;
        }

        $selectedResources->ensure(Subscription::class);
        self::$cachedRequestCollection = $selectedResources;

        return self::$cachedRequestCollection;
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    protected function generateOverviewOfSelectedSubscriptions(Collection $subscriptions, TranslatorInterface $translator): Heading
    {
        $rows = '';
        $lastParentIdShown = null;
        foreach ($subscriptions as $subscription) {
            if ($subscription->parent_subscription_id === null) {
                $lastParentIdShown = $subscription->id;
            }

            $parentRelation = '';
            if ($subscription->parent_subscription_id !== null) {
                if ($lastParentIdShown === $subscription->parent_subscription_id) {
                    $parentRelation = self::CHAR_PARENT_CHILD;
                } else {
                    $parentRelation = $subscription->parent_subscription_id . self::CHAR_PARENT_NEXT_CHILD;
                }
            }

            $rows .= <<<ROW
<tr>
    <td>{$parentRelation} {$subscription->id}</td>
    <td>{$subscription->domain}</td>
    <td>{$subscription->product->name}</td>
    <td>{$translator->translate('subscription.administrative_statuses.' . $subscription->administrative_status)}</td>
    <td>{$subscription->end_date->format(DateTimeFormat::DUTCH)}</td>
</tr>
ROW;
        }

        $tableHeaderTitle = $translator->translate('nova-action.subscriptions.header_selection_overview');

        return Heading::make(<<<TABLE
<h3 class="text-xl">{$tableHeaderTitle}</h3>
<hr />
<table class='w-full divide-y divide-gray-100 dark:divide-gray-700'>
<thead class='bg-gray-50 dark:bg-gray-800'>
    <tr>
        <th style='width: 100px' title='subscription ID'>
            {$translator->translate('subscription.singular')}
        </th>
        <th>{$translator->translate('subscription.attributes.domain')}</th>
        <th>{$translator->translate('product.singular')}</th>
        <th>{$translator->translate('subscription.attributes.administrative_status')}</th>
        <th title='current end date'>{$translator->translate('subscription.attributes.end_date')}</th>
    </tr>
</thead>
<tbody class='divide-y divide-gray-100 dark:divide-gray-700'>
    {$rows}
</tbody>
</table>
TABLE)
            ->asHtml()
        ;
    }

    protected function generateOverviewOfRelatedInvoicesAsHtml(
        Cancellation $cancellation,
        InvoiceRepository $invoiceRepository,
        TranslatorInterface $translator
    ): string {
        $rows = '';

        foreach ($cancellation->getSubscriptions() as $subscription) {
            $foundSubscriptionInvoiceLines = $invoiceRepository->getNonCreditInvoiceLinesForSubscriptionAndEndDate($subscription, $cancellation->getCancellationEndDate($subscription));
            foreach ($foundSubscriptionInvoiceLines as $invoiceLine) {
                $price = Money::format($invoiceLine->net_price);

                $rows .= <<<ROW
<tr>
    <td>{$invoiceLine->subscription?->id}</td>
    <td>{$invoiceLine->id}</td>
    <td>{$invoiceLine->start_date->format(DateTimeFormat::DUTCH)}</td>
    <td>{$invoiceLine->end_date->format(DateTimeFormat::DUTCH)}</td>
    <td>{$price}</td>
</tr>
ROW;
            }
        }

        $tableHeaderTitle = $translator->translate('nova-action.cancel_subscriptions.header_invoices_found');

        return <<<TABLE
<h3 class="text-xl">{$tableHeaderTitle}</h3>
<hr />
<table class='w-full divide-y divide-gray-100 dark:divide-gray-700'>
<thead class='bg-gray-50 dark:bg-gray-800'>
    <tr>
        <td>{$translator->translate('subscription.singular')}</td>
        <td>{$translator->translate('invoice.singular')}</td>
        <td>{$translator->translate('invoice.attributes.start_date')}</td>
        <td>{$translator->translate('invoice.attributes.end_date')}</td>
        <td>{$translator->translate('invoice.attributes.net_price')}</td>
    </tr>
</thead>
<tbody class='divide-y divide-gray-100 dark:divide-gray-700'>
    {$rows}
</tbody>
</table>
TABLE;
    }

    protected function generateOverviewOfCreditInvoicesAsHtml(
        Cancellation $cancellation,
        CreditSubscriptionService $creditSubscriptionService,
        TranslatorInterface $translator
    ): string {
        $creditBatchInvoices = $creditSubscriptionService->getInvoiceLinesToCreditBatch($cancellation);

        $tableHeaderTitle = $translator->translate('nova-action.cancel_subscriptions.header_credit_preview');

        $rows = '';
        foreach ($creditBatchInvoices->getInvoicesToCredit() as $creditInvoice) {
            $price = Money::format(-1 * $creditInvoice->getAmountToCredit());

            $rows .= <<<ROW
<tr>
    <td>{$creditInvoice->getInvoice()->subscription?->id}</td>
    <td>{$creditInvoice->getInvoice()->id}</td>
    <td>{$creditInvoice->getCreditStartDate()->format(DateTimeFormat::DUTCH)}</td>
    <td>{$creditInvoice->getInvoice()->end_date->format(DateTimeFormat::DUTCH)}</td>
    <td>{$price}</td>
</tr>
ROW;
        }

        return <<<TABLE
<h3 class="text-xl">{$tableHeaderTitle}</h3>
<hr />
<table class='w-full divide-y divide-gray-100 dark:divide-gray-700'>
<thead class='bg-gray-50 dark:bg-gray-800'>
    <tr>
        <td>{$translator->translate('subscription.singular')}</td>
        <td>{$translator->translate('invoice.singular')}</td>
        <td>{$translator->translate('invoice.attributes.start_date')}</td>
        <td>{$translator->translate('invoice.attributes.end_date')}</td>
        <td>{$translator->translate('invoice.attributes.net_price')}</td>
    </tr>
</thead>
<tbody class='divide-y divide-gray-100 dark:divide-gray-700'>
    {$rows}
</tbody>
</table>
TABLE;
    }
}
