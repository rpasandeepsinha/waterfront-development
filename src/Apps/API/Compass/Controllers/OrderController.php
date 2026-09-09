<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Response;
use Waterfront\Apps\API\Compass\Requests\ProcessLineItemRequest;
use Waterfront\Apps\API\Compass\Resources\Orders\OrderResource;
use Waterfront\Domain\Orders\Actions\ProcessOrderLineItemAction;
use Waterfront\Domain\Orders\DTO\ProcessOrderLineItemDTO;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Exceptions\OrderLineItemNotProcessableException;
use Waterfront\Domain\Orders\Jobs\ProcessOrderJob;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class OrderController
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ProcessOrderLineItemAction $processOrderLineItemAction,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function show(Order $order): string
    {
        $order->loadMissing([
            'customer',
            'payments',
            'lineItems.order',
            'lineItems.product.productGroup',
            'lineItems.subscription',
            'lineItems.voucherClaim.voucher',
        ]);
        return OrderResource::make($order)->toJson();
    }

    public function retryOrder(Order $order): Response
    {
        if ($order->status !== OrderStatus::ON_HOLD) {
            return new Response(['message' => sprintf('Retry order is not supported for status "%s"', $order->status->value)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->status = OrderStatus::IN_PROGRESS;
        $order->save();

        $this->dispatcher->dispatch(new ProcessOrderJob($order));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function processLineItem(ProcessLineItemRequest $request, OrderLineItem $lineItem): Response
    {
        $processOrderLineItem = new ProcessOrderLineItemDTO(
            $request->boolean('manage_subscriptions'),
            AdministrativeStatus::from($request->administrative_status),
            TechnicalStatus::from($request->technical_status),
            $request->parent_subscription,
        );

        try {
            $this->processOrderLineItemAction->execute($lineItem, $processOrderLineItem);
        } catch (OrderLineItemNotProcessableException $exception) {
            return new Response([
                'message' => $this->translator->translate($exception->translationKey),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response([
            'message' => $this->translator->translate('nova-action.success.invoice_propagated_to_harbor'),
            'errors' => [],
        ], Response::HTTP_OK);
    }
}
