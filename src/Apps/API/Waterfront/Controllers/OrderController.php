<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\ItemNotFoundException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Waterfront\Apps\API\Atlantis\Resources\OrderResource;
use Waterfront\Apps\API\Waterfront\Requests\Cart\CartOrderRequest;
use Waterfront\Domain\Cart\Services\CartService;
use Waterfront\Domain\Cart\Services\ValidationService;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Services\CustomerSharedPaymentService;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\Exceptions\PriceResolvingException;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Authentication\AuthenticationManager;

class OrderController
{
    public function __construct(
        private readonly CustomerSharedPaymentService $paymentService,
        private readonly AuthenticationManager $authenticationManager,
        private readonly OrderService $orderService,
        private readonly SubscriptionService $subscriptionService,
        private readonly CartSerializerFactory $cartSerializerFactory,
        private readonly CartService $cartService,
        private readonly ValidationService $validationService,
        private readonly CalculatePriceService $calculatePriceService,
        private readonly AdministrationFeesManager $administrationFeesManager,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws Exception
     */
    public function order(
        CartOrderRequest $request,
    ): JsonResponse {
        $customer = $this->authenticationManager->getAuthenticatedCustomer();
        $identity = $customer->identitySchema;
        $customer = $customer->customer;

        $serializer = $this->cartSerializerFactory->get();
        /** @var CartOrder $cartOrder */
        $cartOrder = $serializer->denormalize($request->all(), CartOrder::class, 'json');

        try {
            $cartWithoutPrices = $this->cartService->convertCartOrderToProductsWithPeriodsAndPrice($cartOrder);

            $validVouchers = $this->cartService->getValidVoucherCodes($cartOrder->vouchers ?? [], $customer);
            $vouchers = $this->cartService->getVouchers($validVouchers);
            $totalPriceDto = $this->calculatePriceService->calculatePrices($customer, $cartWithoutPrices, $vouchers);
        } catch (ItemNotFoundException|ModelNotFoundException|PriceResolvingException) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $createDirectDebitMandate = $this->cartService->shouldCreateDirectDebitMandate($customer, $cartOrder->paymentMethod);
        $administrationFees = 0;
        if (
            $this->administrationFeesManager->shouldBeChargedWithOrder(
                $customer,
                $cartOrder->paymentMethod,
                $createDirectDebitMandate,
            )
        ) {
            $administrationFees = $this->administrationFeesManager->getAdministrationFees($customer)->price ?? 0;
        }
        $this->validationService->validateOrderTotalPrice($customer, $totalPriceDto, $identity->schemaId === SchemaId::EMPLOYEE, $administrationFees);

        $order = $this->orderService->processCartToOrder($cartOrder, $totalPriceDto, $administrationFees, $customer);

        if ($customer->uuid->toString() !== $identity->id->toString()) {
            $order->ordered_by_uuid = $identity->id;
            $order->ordered_by_metadata = json_encode([
                'email' => $identity->traits?->email,
                'schemaId' => $identity->schemaId->value,
            ], JSON_THROW_ON_ERROR);
            $order->save();
        }

        $status = PaymentStatus::NEEDS_PAYMENT;
        $checkoutUrl = null;

        if ($this->paymentService->requiresDirectPayment($customer, $order, $cartOrder->paymentMethod)) {
            $checkoutUrl = $this->paymentService->checkoutUrlForCart(
                $order,
                $cartOrder->paymentMethod,
                $createDirectDebitMandate,
            );
        } else {
            $status = PaymentStatus::OK;
            $this->subscriptionService->dispatchProcessOrderJob($order);
        }

        return new JsonResponse(
            [
                'transactionId'    => $order->uuid,
                'status'           => $status,
                'data'             => OrderResource::collection($order->lineItems->sortBy('id')),
                'checkout_url'     => $checkoutUrl,
            ]
        );
    }

    /**
     * @throws AuthenticationException
     */
    public function status(Order $order): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        if ($order->customer->id !== $customer->id) {
            throw new NotFoundHttpException();
        }

        if ($order->isPaid()) {
            $status = PaymentStatus::PAID;
        } elseif ($order->isPending() || $order->isOpen()) {
            $status = PaymentStatus::PENDING;
        } else {
            $status = PaymentStatus::FAILED;
        }
        return new JsonResponse([
            'data' => [
                'payment_status' => $status,
            ],
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function retry(Order $order): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        if ($order->customer->id !== $customer->id) {
            throw new NotFoundHttpException();
        }

        $checkoutUrl = $this->paymentService->checkoutUrlForCart($order);

        return new JsonResponse(
            [
                'data' => [
                    'transactionId'    => 'C' . $order->uuid,
                    'checkout_url'     => $checkoutUrl,
                ],
            ]
        );
    }
}
