<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\TransferPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Transfer\StoreRequest;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Subscriptions\Jobs\TransferSubscriptions;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class TransferController
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly Dispatcher $jobDispatcher,
        private readonly TransferPolicy $transferPolicy,
        private readonly TranslatorInterface $translator,
        private readonly AuthenticationManager $authenticationManager,
        private readonly ProductTransferPresenter $productTransferPresenter,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function index(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $transfers = Transfer::where(function ($query) use ($customer): void {
            $query->where('from_customer_id', $customer->id)->orWhere('to_customer_id', $customer->id);
        })->orderBy('id')->get();

        return new JsonResponse(['data' => $this->productTransferPresenter->collectionToArray($transfers, $customer)]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function show(
        Transfer $transfer,
    ): JsonResponse {
        $this->transferPolicy->assertCanShow($transfer);
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        return new JsonResponse(['data' => $this->productTransferPresenter->toArray($transfer, $customer)]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws JsonException
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->transferPolicy->assertCanStore();

        $subscriptionPayload = $request->input('subscriptions');
        assert(is_array($subscriptionPayload));

        $originalCustomer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $subscriptions = $this->transferService->resolveSubscriptions($subscriptionPayload, $originalCustomer);

        if (! $this->transferService->validateSubscriptions($subscriptions, $originalCustomer)) {
            throw new AuthorizationException();
        }

        $receiverNumber = Arr::get($request->all('receiver'), 'receiver.customer_number');
        $receiverEmail = Arr::get($request->all('receiver'), 'receiver.email');

        assert(is_int($receiverNumber));
        assert(is_string($receiverEmail));

        $receiver = $this->transferService->resolveReceiver($receiverEmail, $receiverNumber);

        if ($receiver === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('transfer.customers.store.unable_to_resolve_customer'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subscriptions = $this->transferService->resolveSubscriptions($subscriptionPayload, $originalCustomer);

        if ($subscriptions->count() !== count($subscriptionPayload)) {
            return new JsonResponse([
                'message' => $this->translator->translate('transfer.customers.store.unable_to_resolve_subscriptions'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $transfer = $this->transferService->createTransfer(
            $subscriptions,
            $originalCustomer,
            $receiver,
        );

        return new JsonResponse([
            'id' => $transfer->uuid,
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function accept(Transfer $transfer): JsonResponse
    {
        $this->transferPolicy->assertCanAccept($transfer);

        if (! $transfer->accept()) {
            return new JsonResponse(['message' => $this->translator->translate('transfer.customers.accept-failure')]);
        }

        $transfer = $transfer->fresh();
        assert($transfer instanceof Transfer);

        $this->jobDispatcher->dispatch(new TransferSubscriptions($transfer));

        return new JsonResponse(['message' => $this->translator->translate('transfer.customers.accept-success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function cancel(
        Transfer $transfer,
    ): JsonResponse {
        $this->transferPolicy->assertCanCancel($transfer);

        $canceled = $transfer->cancel();

        if (! $canceled) {
            return new JsonResponse([
                'message' => $this->translator->translate('transfer.customers.cancel.incorrect_status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('transfer.customers.cancel.success'),
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function reject(
        Transfer $transfer,
    ): JsonResponse {
        $this->transferPolicy->assertCanReject($transfer);

        $rejected = $transfer->reject();

        if (! $rejected) {
            return new JsonResponse([
                'message' => $this->translator->translate('transfer.customers.reject.incorrect_status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('transfer.customers.reject.success'),
        ]);
    }
}
