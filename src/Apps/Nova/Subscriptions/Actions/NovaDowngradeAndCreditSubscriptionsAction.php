<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Exception;
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
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Actions\DowngradeSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation as Creditation;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\CreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class NovaDowngradeAndCreditSubscriptionsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly CreditSubscriptionService $creditSubscriptionService,
        private readonly DowngradeSubscriptionsAction $downgradeSubscriptionsAction,
        private readonly ProductRepository $productRepository,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
        private readonly SubscriptionChangeService $subscriptionChangeService,
        private readonly LoggerInterface $logger,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => $this->onlyForSingleCustomer($request),
        );
        $this->modalSize = '4xl';
        $this->confirmText($this->translator->translate('manual-downgrade.confirmation-message'));
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.downgrade_subscription.name');
    }

    /**
     * @return array<int,Heading|Select|Text|Date|NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        $allAffectedSubscriptions = $this->getAllAffectedSubscriptionsFromSelection($request->selectedResources());

        $cancelCreditOptionsTitle = $this->translator->translate('nova-action.downgrade_subscription.header_options');
        $reasonOptions = [];

        foreach (SubscriptionCancelReason::cases() as $reason) {
            $reasonOptions[$reason->value] = $this->translator->translate(
                'cancel_subscriptions.reason.' . strtolower($reason->name),
            );
        }

        $potentialDowngrades = $this->productAllowedChangeRepository->getPotentialDowngrades($allAffectedSubscriptions->firstOrFail()->product);

        return [
            $this->generateOverviewOfSelectedSubscriptions($allAffectedSubscriptions, $this->translator),

            Heading::make("<h3 class=\"text-xl\">{$cancelCreditOptionsTitle}</h3><hr />")->asHtml(),
            Select::make($this->translator->translate('nova-action.downgrade_subscription.product'), 'product_id')
                ->rules('required')
                ->options(
                    $potentialDowngrades->keyBy('id')->map(fn ($product): string => $product->name),
                )
                ->displayUsingLabels(),
            Select::make($this->translator->translate('nova-action.downgrade_subscription.reason'), 'reason')
                ->rules('required')
                ->options(
                    $reasonOptions,
                ),
            Text::make($this->translator->translate('nova-action.downgrade_subscription.reason_other'), 'reason_other')
                ->hide()
                ->dependsOn(
                    'reason',
                    static function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('reason') === SubscriptionCancelReason::REASON_OTHER->value) {
                            $field->show()->rules('required');
                        }
                    },
                ),
            NovaBoolField::make($this->translator->translate('nova-action.downgrade_subscription.credit'), 'credit'),

            $this->allRelatedInvoicesField($allAffectedSubscriptions),
            $this->previewCreditInvoicesField($allAffectedSubscriptions),
        ];
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();

        if (! $this->subscriptionIsDowngradable($subscription)) {
            return self::danger(
                $this->translator->translate(
                    'nova-action.downgrade_subscription.failure',
                ),
            );
        }

        try {
            $creditation = $this->getCreditationDtoFromInput($models, $fields);
            $productId = $fields->get('product_id');
            assert(is_string($productId));
            $newProduct = $this->productRepository->findProductById((int) $productId);

            /* We perform the credit action first because this is doing a synchronous API
             * call to Harbor to generate a credit invoice. If this fails, there is no need
             * to downgrade any subscriptions at all.
             */
            if ($creditation->shouldCreditRelatedInvoices()) {
                $this->creditSubscriptionService->creditSubscriptions($creditation);
            }

            $technicalResult = $this->downgradeSubscriptionsAction->execute($subscription, $newProduct);

            if ($technicalResult->status === SubscriptionChangeResult::STATUS_ERROR) {
                $this->subscriptionChangeService->storeChangeRecord(
                    $subscription,
                    $newProduct,
                    ProductChangeType::DOWNGRADE,
                    SubscriptionChangeStatus::EXECUTION_FAILED,
                );

                $this->logger->error(
                    'Technical downgrade failed, administrative downgrade skipped for subscription {subscription.uuid}',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::META => [
                            'error_code' => $technicalResult->errorCode,
                            'error_message' => $technicalResult->errorMessage,
                        ],
                    ],
                );

                return self::danger($this->translator->translate(
                    'nova-action.downgrade_subscription.technical_failure',
                ));
            }

            $this->subscriptionChangeService->change(
                ProductChangeType::DOWNGRADE,
                $subscription,
                $newProduct,
                false,
                false,
            );
        } catch (InvalidArgumentException|CreditSubscriptionsException|SubscriptionChangeException $exception) {
            $this->logger->critical(
                sprintf('Nova action for downgrade and credit failed with exception: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return self::danger('Action failed: ' . $exception->getMessage());
        } catch (NotImplementedException) {
            return self::danger($this->translator->translate('nova-action.downgrade_subscription.not_implemented'));
        } catch (Exception $exception) {
            $this->logger->critical(
                sprintf('Nova action for downgrade and credit failed with exception: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw $exception;
        }

        return self::message($this->translator->translate('nova-action.downgrade_subscription.success'));
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
        }

        return $subscriptions->filter()->unique(fn (Subscription $subscription): int => $subscription->id);
    }

    /**
     * @param Collection<int, Subscription>         $subscriptions
     * @param FormData<string, string>|ActionFields $data
     */
    private function getCreditationDtoFromInput(
        Collection $subscriptions,
        FormData|ActionFields $data,
    ): Creditation {
        $cancelReasonString = (string) $data->string('reason');
        Assert::stringNotEmpty($cancelReasonString);
        $cancelReason = SubscriptionCancelReason::from($cancelReasonString);

        $cancelReasonOther = $data->get('reason_other');
        Assert::nullOrStringNotEmpty($cancelReasonOther);

        $cancelType = SubscriptionCancelType::CANCEL_DOWNGRADE;

        $cancelTypeOtherDate = CarbonImmutable::now();

        $credit = boolval($data->get('credit'));

        return new Creditation(
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
                ['reason'],
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
        $reason = $formData->get('reason');
        if ($reason === null) {
            $field->hide();

            return;
        }

        $creditation = $this->getCreditationDtoFromInput($allAffectedSubscriptions, $formData);

        $field->withMeta([
            'value' => $this->generateOverviewOfRelatedInvoicesAsHtml(
                $creditation,
                $this->invoiceRepository,
                $this->translator,
            ),
        ]);
        $field->show();
    }

    private function subscriptionIsDowngradable(Subscription $subscription): bool
    {
        return ! in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true);
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
                ['reason', 'credit'],
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
        $reason = $formData->get('reason');
        if ($reason === null) {
            return;
        }

        $creditation = $this->getCreditationDtoFromInput($allAffectedSubscriptions, $formData);

        if (! $creditation->shouldCreditRelatedInvoices()) {
            $field->hide();

            return;
        }

        $field->withMeta([
            'value' => $this->generateOverviewOfCreditInvoicesAsHtml(
                $creditation,
                $this->creditSubscriptionService,
                $this->translator,
            ),
        ]);
        $field->show();
    }
}
