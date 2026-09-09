<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use JsonException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaSetEndDateAndDetermineNextBillingDateAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly StoreNoteAction $storeNote,
        private readonly PriceResolver $priceResolver,
    ) {
        $this->sole();

        $this->canSee(
            fn (NovaRequest $request): bool => $this->onlyForSingleSubscription($request)
        );
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.update-billing-and-contract-dates-and-periods.name');
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        $subscription = $subscriptions->first();

        if ($subscription === null) {
            return self::message('Given subscription could not be retrieved from the database');
        }

        $newContractPeriod = $fields->get('contract_period', 0);
        $newBillingPeriod = $fields->get('billing_period', 0);
        $newEndDate = $fields->get('end_date', '');
        $newNextBillingDate = $fields->get('next_billing_date', '');
        $reason = $fields->get('reason', '');

        assert(is_string($newContractPeriod));
        assert(is_string($newBillingPeriod));
        assert(is_string($newEndDate));
        assert(is_string($newNextBillingDate));
        assert(is_string($reason));

        $newContractPeriod = intval($newContractPeriod);
        $newBillingPeriod = intval($newBillingPeriod);

        Assert::positiveInteger($newContractPeriod);
        Assert::positiveInteger($newBillingPeriod);
        Assert::stringNotEmpty($newEndDate);
        Assert::stringNotEmpty($newNextBillingDate);
        Assert::stringNotEmpty($reason);

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($subscription->product)], $subscription->customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        try {
            $priceList->getProductPrice($subscription->product->slug, $newContractPeriod, $newBillingPeriod);
        } catch (ItemNotFoundException) {
            throw ValidationException::withMessages([
                'billing_period' => $this->translator->translate(
                    'nova-action.update-billing-and-contract-dates-and-periods.no-matching-price-found'
                ),
                'contract_period' => $this->translator->translate(
                    'nova-action.update-billing-and-contract-dates-and-periods.no-matching-price-found'
                ),
            ]);
        }

        $formattedEndDate = CarbonImmutable::createFromFormat('Y-m-d', $newEndDate);
        $formattedNextBillingDate = CarbonImmutable::createFromFormat('Y-m-d', $newNextBillingDate);
        Assert::isInstanceOf($formattedEndDate, CarbonImmutable::class);
        Assert::isInstanceOf($formattedNextBillingDate, CarbonImmutable::class);
        $formattedEndDate = $formattedEndDate->startOfDay();
        $formattedNextBillingDate = $formattedNextBillingDate->startOfDay();

        $newNextBillingDateOptions = $this->calculatePossibleOptions($newBillingPeriod, $formattedEndDate);
        if (
            count($newNextBillingDateOptions) === 0
            || in_array($formattedNextBillingDate->format('Y-m-d'), $newNextBillingDateOptions, true)
        ) {
            throw ValidationException::withMessages([
                'next_billing_date' => $this->translator->translate(
                    'nova-action.update-billing-and-contract-dates-and-periods.no-next-billing-date-found'
                ),
            ]);
        }

        $this->persistSubscriptionUpdates(
            $newBillingPeriod,
            $subscription,
            $newContractPeriod,
            $formattedEndDate,
            $formattedNextBillingDate,
            $reason
        );

        return self::message($this->translator->translate('nova-action.update-billing-and-contract-dates-and-periods.success'));
    }

    /**
     * @return array<Number|Date|Select|Textarea>
     */
    public function fields(NovaRequest $request): array
    {
        $subscription = Subscription::where('id', $request->selectedResourceIds()?->first())->first();

        return [
            Number::make($this->translator->translate('subscription.attributes.contract_period'), 'contract_period')
                ->default(fn () => $subscription->contract_period ?? 12),
            Number::make($this->translator->translate('subscription.attributes.billing_period'), 'billing_period')
                ->default(fn () => $subscription->billing_period ?? 12),
            Date::make($this->translator->translate('subscription.attributes.end_date'), 'end_date')
                ->default(fn () => $subscription?->end_date->format('Y-m-d') ?? CarbonImmutable::now()->format('Y-m-d'))
                ->required()
                ->rules(['date', 'after:today']),
            Select::make($this->translator->translate('subscription.attributes.next_billing_date'), 'next_billing_date')
                ->dependsOn(
                    ['end_date', 'contract_period', 'billing_period'],
                    function (Select $field, NovaRequest $novaRequest, FormData $formData) {
                        $endDate = CarbonImmutable::createFromFormat('Y-m-d', $formData->string('end_date')->toString());
                        Assert::isInstanceOf($endDate, CarbonImmutable::class);
                        $options = $this->calculatePossibleOptions($formData->integer('billing_period'), $endDate);
                        $field->options($options);
                    }
                )->required()
                ->default(fn () => $subscription?->next_billing_date->format('Y-m-d'))
                ->rules(['date', 'after:today', 'before_or_equal:end_date']),

            Textarea::make($this->translator->translate('nova-action.update-billing-and-contract-dates-and-periods.reason'), 'reason')
                ->required()
                ->rules(['string', 'min:1'])
                ->help($this->translator->translate('nova-action.update-billing-and-contract-dates-and-periods.reason-hint')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function calculatePossibleOptions(?int $billingPeriod, ?CarbonImmutable $endDate): array
    {
        if ($endDate === null || $billingPeriod === null) {
            return [];
        }

        $billingDatePossibilities = [];
        $nextPossibility = CarbonImmutable::now();

        $currentPossibility = $endDate;
        while ($currentPossibility >= $nextPossibility) {
            $billingDatePossibilities[$currentPossibility->format('Y-m-d')] = $currentPossibility->format('d-m-Y');
            $currentPossibility = $currentPossibility->subMonths($billingPeriod);
        }

        return array_reverse($billingDatePossibilities);
    }

    /**
     * @throws JsonException
     */
    private function persistSubscriptionUpdates(int $newBillingPeriod, Subscription $subscription, int $newContractPeriod, CarbonImmutable $newEndDate, CarbonImmutable $newNextBillingDate, string $reason): void
    {
        $subscription->billing_period = $newBillingPeriod;
        $subscription->contract_period = $newContractPeriod;
        $subscription->end_date = $newEndDate;
        $subscription->next_billing_date = $newNextBillingDate;
        $subscription->update();

        $this->storeNote->execute(
            $reason,
            $subscription,
        );
    }
}
