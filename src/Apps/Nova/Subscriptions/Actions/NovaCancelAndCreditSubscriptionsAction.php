<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\CancelCreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Exceptions\CreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class NovaCancelAndCreditSubscriptionsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly CreditSubscriptionService $creditSubscriptionService,
        private readonly CancelSubscriptionsAction $cancelCreditSubscriptionsAction,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly LoggerInterface $logger,
    ) {
        $this->confirmText('');
        $this->canSee(
            fn (NovaRequest $request): bool => $this->onlyForSingleCustomer($request),
        );
        $this->modalSize = '4xl';
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->translator->translate('nova-action.cancel_subscriptions.name');
    }

    /**
     * @return array<int,Heading|Select|Text|Date|NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        $maxEndDate = CarbonImmutable::now()->addYear();
        $allAffectedSubscriptions = new Collection();
        if ($request->isActionRequest()) {
            $allAffectedSubscriptions = $this->getAllAffectedSubscriptionsFromSelection($request->selectedResources());
            $maxEndDate = $this->getMaxSelectableEndDateFromSubscriptions($allAffectedSubscriptions);
        }

        $cancelCreditOptionsTitle = $this->translator->translate('nova-action.cancel_subscriptions.header_options');
        $reasonOptions = [];

        foreach (SubscriptionCancelReason::cases() as $reason) {
            $reasonOptions[$reason->value] = $this->translator->translate(
                'cancel_subscriptions.reason.' . strtolower($reason->name),
            );
        }

        return [
            $this->generateOverviewOfSelectedSubscriptions($allAffectedSubscriptions, $this->translator),

            Heading::make("<h3 class=\"text-xl\">{$cancelCreditOptionsTitle}</h3><hr />")->asHtml(),

            Select::make($this->translator->translate('nova-action.cancel_subscriptions.reason'), 'reason')->options(
                $reasonOptions,
            ),
            Text::make($this->translator->translate('nova-action.cancel_subscriptions.reason_other'), 'reason_other')
                ->hide()
                ->dependsOn(
                    'reason',
                    static function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('reason') === SubscriptionCancelReason::REASON_OTHER->value) {
                            $field->show()->rules('required');
                        }
                    },
                ),

            Select::make($this->translator->translate('nova-action.cancel_subscriptions.type'), 'type')
                ->hide()
                ->dependsOn(
                    'reason',
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('reason') === null) {
                            return;
                        }

                        $options = [
                            SubscriptionCancelType::CANCEL_END_DATE->value => $this->translator->translate(
                                'cancel_subscriptions.cancel_type.'
                                    . strtolower(SubscriptionCancelType::CANCEL_END_DATE->name),
                            ),
                            SubscriptionCancelType::CANCEL_OTHER->value => $this->translator->translate(
                                'cancel_subscriptions.cancel_type.'
                                    . strtolower(SubscriptionCancelType::CANCEL_OTHER->name),
                            ),
                        ];

                        $field->options($options)->show();
                    },
                ),

            Date::make(
                $this->translator->translate('nova-action.cancel_subscriptions.type_other_date'),
                'type_other_date',
            )
                ->default(CarbonImmutable::today()->format(DateTimeFormat::DATE))
                ->min(CarbonImmutable::now()->subYear())
                ->max($maxEndDate)
                ->hide()
                ->dependsOn(
                    'type',
                    static function (Date $field, NovaRequest $request, FormData $formData): void {
                        if ($formData->get('type') === SubscriptionCancelType::CANCEL_OTHER->value) {
                            $field
                                ->show()
                                ->rules('required')
                                ->setValue(
                                    $formData->get(
                                        'type_other_date',
                                    ) ?? CarbonImmutable::today()->format(DateTimeFormat::DATE),
                                );
                        }
                    },
                ),

            NovaBoolField::make($this->translator->translate('nova-action.cancel_subscriptions.credit'), 'credit')
                ->hide()
                ->dependsOn(
                    ['type', 'reason'],
                    static function (NovaBoolField $field, NovaRequest $request, FormData $formData): void {
                        if (
                            $formData->get('reason') === SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY->value
                        ) {
                            return;
                        }

                        $type = $formData->get('type');
                        if (in_array(
                            $type,
                            [
                                SubscriptionCancelType::CANCEL_OTHER->value,
                            ],
                            true,
                        )) {
                            $field->show()->rules('required');
                        }

                        if ($formData->get('reason') === null) {
                            return;
                        }

                        $reasonValue = $formData->get('reason');
                        if (is_string($reasonValue)) {
                            $enum = SubscriptionCancelReason::from($reasonValue);
                            $field->setValue($enum->enforcesToCreditFully());
                        }
                    },
                ),

            $this->allRelatedInvoicesField($allAffectedSubscriptions),

            $this->previewCreditInvoicesField($allAffectedSubscriptions),
        ];
    }

    /**
     * Perform the action on the given models.
     *
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if (! $this->subscriptionsAreForSingleCustomer($models)) {
            return self::danger(
                $this->translator->translate('nova-action.cancel_subscriptions.failure_multi_customers'),
            );
        }

        $nonCancellable = $this->filterNoneCancellableSubscriptionsFromSelection($models);

        if (count($nonCancellable) >= 1) {
            return self::danger(
                $this->translator->translate(
                    'nova-action.cancel_subscriptions.failure',
                    ['subscriptions' => $this->formatFailedCancellations(array_map(
                        fn (Subscription $subscription): int => $subscription->id,
                        $nonCancellable,
                    ))],
                ),
            );
        }

        if (! $this->childSubscriptionsCanBeCancelled($models)) {
            return self::danger($this->translator->translate(
                'nova-action.cancel_subscriptions.failure_cancel_with_parent',
            ));
        }

        $allAffectedSubscriptions = $this->getAllAffectedSubscriptionsFromSelection($models);

        try {
            $cancellation = $this->getCancellationDtoFromInput($allAffectedSubscriptions, $fields);

            /* We perform the credit action first because this is doing a synchronous API
             * call to Harbor to generate a credit invoice. If this fails, there is no need
             * to cancel any subscriptions at all.
             */
            if ($cancellation->shouldCreditRelatedInvoices()) {
                $this->creditSubscriptionService->creditSubscriptions($cancellation);
            }

            $this->cancelCreditSubscriptionsAction->execute($cancellation);
        } catch (InvalidArgumentException|CreditSubscriptionsException|CancelCreditSubscriptionsException $exception) {
            $this->logger->critical(
                'Nova action for cancel and credit failed with exception',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return self::danger('Action failed: ' . $exception->getMessage());
        }

        return self::message($this->translator->translate('nova-action.cancel_subscriptions.success'));
    }

    /**
     * @param int[] $failedCancellations
     */
    private function formatFailedCancellations(array $failedCancellations): string
    {
        return implode(',<br />', $failedCancellations);
    }

    /**
     * @param Collection<int, Subscription>|null $selectedResources
     *
     * @return Collection<int, Subscription>
     */
    private function getAllAffectedSubscriptionsFromSelection(?Collection $selectedResources): Collection
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = new Collection();
        if (! $selectedResources instanceof Collection) {
            return $subscriptions;
        }

        foreach ($selectedResources as $subscription) {
            $subscriptions->add($subscription);

            foreach ($this->subscriptionRepository->getDependentSubscriptions(
                $subscription,
            ) as $dependentSubscription) {
                if (
                    $dependentSubscription->administrative_status === AdministrativeStatus::ARCHIVED->value
                    || $dependentSubscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                ) {
                    continue;
                }

                $subscriptions->add($dependentSubscription);
            }
        }

        return $subscriptions->filter()->unique(fn (Subscription $subscription): int => $subscription->id);
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function getMaxSelectableEndDateFromSubscriptions(Collection $subscriptions): CarbonImmutable
    {
        return $subscriptions->reduce(
            fn (CarbonImmutable $maxEndDate, Subscription $subscription): CarbonImmutable => $subscription->end_date
                > $maxEndDate
                    ? $subscription->end_date
                    : $maxEndDate,
            CarbonImmutable::now(),
        );
    }

    private function getCancelTypeDateOther(SubscriptionCancelType $from, ?string $inputDateString): ?CarbonImmutable
    {
        if ($from === SubscriptionCancelType::CANCEL_END_DATE) {
            return null;
        }

        if ($inputDateString === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $inputDateString);
        Assert::isInstanceOf($date, CarbonImmutable::class, 'Couldn\'t load the date from the input form.');

        return $date;
    }

    /**
     * @param Collection<int, Subscription>         $subscriptions
     * @param FormData<string, string>|ActionFields $data
     */
    private function getCancellationDtoFromInput(
        Collection $subscriptions,
        FormData|ActionFields $data,
    ): Cancellation {
        $cancelReasonString = (string) $data->string('reason');
        Assert::stringNotEmpty($cancelReasonString);
        $cancelReason = SubscriptionCancelReason::from($cancelReasonString);

        $cancelReasonOther = $data->get('reason_other');
        Assert::nullOrStringNotEmpty($cancelReasonOther);

        $cancelTypeString = (string) $data->string('type');
        $cancelType = SubscriptionCancelType::from($cancelTypeString);

        $typeOtherDateString = $data->get('type_other_date');
        Assert::nullOrStringNotEmpty($typeOtherDateString);
        $cancelTypeOtherDate = $this->getCancelTypeDateOther($cancelType, $typeOtherDateString);

        $credit = $cancelType->allowedToCredit() && $cancelReason->allowedToCredit() && boolval($data->get('credit'));

        return new Cancellation(
            $subscriptions,
            $cancelReason,
            $cancelReasonOther,
            $cancelType,
            $cancelTypeOtherDate,
            $credit,
        );
    }

    /**
     * @param Collection<int, Subscription> $allAffectedSubscriptions
     */
    private function allRelatedInvoicesField(Collection $allAffectedSubscriptions): Heading
    {
        return Heading::make('related_invoices')
            ->hide()
            ->asHtml()
            ->dependsOn(
                ['reason', 'type', 'type_other_date'],
                fn (Heading $field, NovaRequest $request, FormData $formData) => $this->updateOverviewOfRelatedInvoices(
                    $field,
                    $formData,
                    $allAffectedSubscriptions,
                ),
            );
    }

    /**
     * @param Collection<int, Subscription> $allAffectedSubscriptions
     * @param FormData<string, string>      $formData
     */
    private function updateOverviewOfRelatedInvoices(
        Heading $field,
        FormData $formData,
        Collection $allAffectedSubscriptions,
    ): void {
        if ($formData->get('reason') === SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY->value) {
            return;
        }

        $cancelType = SubscriptionCancelType::tryFrom((string) $formData->string('type'));
        if ($cancelType === null || $cancelType === SubscriptionCancelType::CANCEL_END_DATE) {
            $field->hide();

            return;
        }

        $cancellation = $this->getCancellationDtoFromInput($allAffectedSubscriptions, $formData);

        $field->withMeta([
            'value' => $this->generateOverviewOfRelatedInvoicesAsHtml(
                $cancellation,
                $this->invoiceRepository,
                $this->translator,
            ),
        ]);
        $field->show();
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function subscriptionsAreForSingleCustomer(Collection $subscriptions): bool
    {
        $customerNumbers = $subscriptions
            ->unique(
                fn (Subscription $subscription) => $subscription->customer_id,
            )
            ->pluck('customer_id');

        return $customerNumbers->count() === 1;
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return array<int, Subscription>
     */
    private function filterNoneCancellableSubscriptionsFromSelection(Collection $subscriptions): array
    {
        $nonCancellable = [];

        foreach ($subscriptions as $subscription) {
            if (
                $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                || in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true)
            ) {
                $nonCancellable[] = $subscription;
            }
        }

        return $nonCancellable;
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function childSubscriptionsCanBeCancelled(Collection $subscriptions): bool
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->parent_subscription_id === null) {
                continue;
            }

            $allowCancelAsChild = $this->productSpecRepository->booleanSpecificationIsTrue(
                product: $subscription->product,
                specName: ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD,
            );

            if (! $allowCancelAsChild) {
                // This child subscription is not allowed to be cancelled separately from the parent.
                return false;
            }
        }

        return true;
    }

    /**
     * @param Collection<int, Subscription> $allAffectedSubscriptions
     */
    private function previewCreditInvoicesField(Collection $allAffectedSubscriptions): Heading
    {
        return Heading::make('credit_preview')
            ->hide()
            ->asHtml()
            ->dependsOn(
                ['reason', 'type', 'type_other_date', 'credit'],
                fn (Heading $field, NovaRequest $request, FormData $formData) => $this->updateCreditInvoicesOverview(
                    $field,
                    $formData,
                    $allAffectedSubscriptions,
                ),
            );
    }

    /**
     * @param Collection<int, Subscription> $allAffectedSubscriptions
     * @param FormData<string, string>      $formData
     */
    private function updateCreditInvoicesOverview(
        Heading $field,
        FormData $formData,
        Collection $allAffectedSubscriptions,
    ): void {
        $cancelType = SubscriptionCancelType::tryFrom((string) $formData->string('type'));
        if ($cancelType === null) {
            return;
        }

        $cancelReason = SubscriptionCancelReason::tryFrom((string) $formData->string('reason'));
        if ($cancelReason === null || $cancelReason === SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY) {
            return;
        }

        if (! $cancelReason->allowedToCredit() || ! $cancelType->allowedToCredit()) {
            $field->withMeta([
                'value' =>
                    '<i>'
                        . $this->translator->translate('nova-action.cancel_subscriptions.type.end_date.no_credit')
                        . '</i>',
            ]);
            $field->show();

            return;
        }

        $cancellation = $this->getCancellationDtoFromInput($allAffectedSubscriptions, $formData);

        if (! $cancellation->shouldCreditRelatedInvoices()) {
            $field->hide();

            return;
        }

        $field->withMeta([
            'value' => $this->generateOverviewOfCreditInvoicesAsHtml(
                $cancellation,
                $this->creditSubscriptionService,
                $this->translator,
            ),
        ]);
        $field->show();
    }
}
