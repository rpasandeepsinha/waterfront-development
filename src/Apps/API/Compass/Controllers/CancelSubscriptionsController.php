<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\DTO\CancellationPreviewDTO;
use Waterfront\Apps\API\Compass\DTO\CancellationProblemDTO;
use Waterfront\Apps\API\Compass\Requests\CancelSubscriptionsRequest;
use Waterfront\Apps\API\Compass\Requests\CheckCancelSubscriptionsRequest;
use Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation\CancellationPreviewResource;
use Waterfront\Domain\Invoices\Models\Invoice;
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
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CancelSubscriptionsController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly CreditSubscriptionService $creditSubscriptionService,
        private readonly CancelSubscriptionsAction $cancelSubscriptionsAction,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[RequirePermission(Permissions::CAN_CANCEL_AND_CREDIT)]
    public function cancelAndCredit(CancelSubscriptionsRequest $request): Response
    {
        $selectedSubscriptions = $this->subscriptionRepository->getSubscriptionsByUuid($request->subscription_uuids);

        $selectedSubscriptions->loadMissing(['product.productGroup', 'product.productSpecs', 'children']);

        $this->assertCancellable($selectedSubscriptions);

        $cancelReason = $request->cancelReason();
        assert($cancelReason instanceof SubscriptionCancelReason);
        $cancelType = $request->cancelType();
        assert($cancelType instanceof SubscriptionCancelType);
        $cancelReasonOther = $request->string('reason_other')->toString();

        try {
            $cancellation = new Cancellation(
                $this->expandWithDependentSubscriptions($selectedSubscriptions),
                $cancelReason,
                $cancelReasonOther === '' ? null : $cancelReasonOther,
                $cancelType,
                $this->resolveSelectedCancelEndDate($cancelType, $request->string('type_other_date')->toString()),
                $cancelType->allowedToCredit() && $cancelReason->allowedToCredit() && $request->boolean('credit'),
            );

            /* We perform the credit action first because this is doing a synchronous API
             * call to Harbor to generate a credit invoice. If this fails, there is no need
             * to cancel any subscriptions at all.
             */
            if ($cancellation->shouldCreditRelatedInvoices()) {
                $this->creditSubscriptionService->creditSubscriptions($cancellation);
            }

            $this->cancelSubscriptionsAction->execute($cancellation);
        } catch (InvalidArgumentException|CreditSubscriptionsException|CancelCreditSubscriptionsException $exception) {
            $this->logger->error(
                'Cancelling and crediting subscriptions failed with exception',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new Response(
                ['message' => $this->translator->translate('subscription.cancel.failure_execution')],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[RequirePermission(Permissions::CAN_CANCEL_AND_CREDIT)]
    public function check(CheckCancelSubscriptionsRequest $request): JsonResponse
    {
        $selectedSubscriptions = $this->subscriptionRepository->getSubscriptionsByUuid($request->subscription_uuids);

        $selectedSubscriptions->loadMissing(['product.productGroup', 'product.productSpecs', 'children']);

        $problems = $this->getCancellationProblems($selectedSubscriptions);

        $affectedSubscriptions = $this->expandWithDependentSubscriptions($selectedSubscriptions);

        $cancelReason = $request->cancelReason();
        $cancelType = $request->cancelType();
        $creditAllowed =
            $problems === [] && $cancelReason?->allowedToCredit() === true && $cancelType?->allowedToCredit() === true;

        try {
            $cancellation = $creditAllowed
                ? $this->buildCancellationForPreview($affectedSubscriptions, $request, $cancelReason, $cancelType)
                : null;

            $creditApplied = $cancellation?->shouldCreditRelatedInvoices() ?? false;

            $preview = new CancellationPreviewDTO(
                subscriptions: $affectedSubscriptions->all(),
                blockingProblems: $problems,
                creditAllowed: $creditAllowed,
                creditApplied: $creditApplied,
                maxSelectableEndDate: $this->getMaxSelectableEndDate($affectedSubscriptions),
                creditableInvoiceLines: $cancellation === null ? [] : $this->getCreditableInvoiceLines($cancellation),
                creditInvoiceLines: $creditApplied
                    ? $this->creditSubscriptionService
                        ->getInvoiceLinesToCreditBatch($cancellation)
                        ->getInvoicesToCredit()
                    : [],
            );
        } catch (InvalidArgumentException $exception) {
            $this->logger->error(
                'Previewing the cancellation and credit of subscriptions failed with exception',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse(
                ['message' => $this->translator->translate('subscription.cancel.failure_preview')],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(CancellationPreviewResource::make($preview)->resolve());
    }

    /**
     * @param EloquentCollection<int,Subscription> $subscriptions
     *
     * @throws ValidationException
     */
    public function assertCancellable(EloquentCollection $subscriptions): void
    {
        $problems = $this->getCancellationProblems($subscriptions);

        if ($problems === []) {
            return;
        }

        throw ValidationException::withMessages([
            'subscription_uuids' => array_values(array_unique(array_map(
                fn (CancellationProblemDTO $problem): string => $problem->message,
                $problems,
            ))),
        ]);
    }

    /**
     * @param Collection<int, Subscription> $selectedSubscriptions
     *
     * @return Collection<int, Subscription>
     */
    public function expandWithDependentSubscriptions(Collection $selectedSubscriptions): Collection
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = new Collection();

        foreach ($selectedSubscriptions as $subscription) {
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
     * @param EloquentCollection<int,Subscription> $subscriptions
     *
     * @return list<CancellationProblemDTO>
     */
    private function getCancellationProblems(EloquentCollection $subscriptions): array
    {
        $multipleCustomersProblem = $this->findMultipleCustomersProblem($subscriptions);

        return [
            ...($multipleCustomersProblem instanceof CancellationProblemDTO ? [$multipleCustomersProblem] : []),
            ...$this->findNonCancellableProblems($subscriptions),
            ...$this->findChildCancellationProblems($subscriptions),
        ];
    }

    /**
     * @param Collection<int, Subscription> $affectedSubscriptions
     */
    private function buildCancellationForPreview(
        Collection $affectedSubscriptions,
        CheckCancelSubscriptionsRequest $request,
        SubscriptionCancelReason $cancelReason,
        SubscriptionCancelType $cancelType,
    ): Cancellation {
        $cancelReasonOther = $request->string('reason_other')->toString();

        return new Cancellation(
            $affectedSubscriptions,
            $cancelReason,
            $cancelReasonOther === '' ? null : $cancelReasonOther,
            $cancelType,
            $this->resolveSelectedCancelEndDate($cancelType, $request->string('type_other_date')->toString()),
            $request->boolean('credit'),
        );
    }

    /**
     * @return Invoice[]
     */
    private function getCreditableInvoiceLines(Cancellation $cancellation): array
    {
        $invoiceLines = [];

        foreach ($cancellation->getSubscriptions() as $subscription) {
            $subscriptionInvoiceLines = $this->invoiceRepository->getNonCreditInvoiceLinesForSubscriptionAndEndDate(
                $subscription,
                $cancellation->getCancellationEndDate($subscription),
            );

            foreach ($subscriptionInvoiceLines as $invoiceLine) {
                $invoiceLines[] = $invoiceLine;
            }
        }

        return $invoiceLines;
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function getMaxSelectableEndDate(Collection $subscriptions): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $maxEndDate = $subscriptions->max('end_date');

        return $maxEndDate instanceof CarbonImmutable && $maxEndDate->isAfter($now) ? $maxEndDate : $now;
    }

    private function resolveSelectedCancelEndDate(
        SubscriptionCancelType $cancelType,
        string $selectedDate,
    ): ?CarbonImmutable {
        if ($cancelType === SubscriptionCancelType::CANCEL_END_DATE) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $selectedDate);
        Assert::isInstanceOf($date, CarbonImmutable::class, 'Couldn\'t load the date from the input.');

        return $date;
    }

    /**
     * @param EloquentCollection<int,Subscription> $subscriptions
     */
    private function findMultipleCustomersProblem(EloquentCollection $subscriptions): ?CancellationProblemDTO
    {
        $customerIds = $subscriptions
            ->unique(
                fn (Subscription $subscription): int => $subscription->customer_id,
            )
            ->pluck('customer_id');

        if ($customerIds->count() === 1) {
            return null;
        }

        return new CancellationProblemDTO(
            null,
            $this->translator->translate('subscription.cancel.failure_multi_customers'),
        );
    }

    /**
     * @param EloquentCollection<int,Subscription> $subscriptions
     *
     * @return list<CancellationProblemDTO>
     */
    private function findNonCancellableProblems(EloquentCollection $subscriptions): array
    {
        $problems = [];

        foreach ($subscriptions as $subscription) {
            if (
                $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                || in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true)
            ) {
                $problems[] = new CancellationProblemDTO(
                    $subscription->id,
                    $this->translator->translate('subscription.cancel.failure_non_cancellable'),
                );
            }
        }

        return $problems;
    }

    /**
     * @param EloquentCollection<int,Subscription> $subscriptions
     *
     * @return list<CancellationProblemDTO>
     */
    private function findChildCancellationProblems(EloquentCollection $subscriptions): array
    {
        $problems = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->parent_subscription_id === null) {
                continue;
            }

            $allowCancelAsChild = $this->productSpecRepository->booleanSpecificationIsTrue(
                product: $subscription->product,
                specName: ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD,
            );

            if ($allowCancelAsChild) {
                continue;
            }

            $parentIsPresent = $subscriptions->contains(
                fn (Subscription $iteratedSubscription) => (
                    $iteratedSubscription->id === $subscription->parent_subscription_id
                ),
            );

            if ($parentIsPresent) {
                continue;
            }

            $problems[] = new CancellationProblemDTO(
                $subscription->id,
                $this->translator->translate('subscription.cancel.failure_cancel_with_parent'),
            );
        }

        return $problems;
    }
}
