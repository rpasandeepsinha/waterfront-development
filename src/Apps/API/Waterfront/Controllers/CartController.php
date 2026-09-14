<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Requests\Cart\CartCheckRequest;
use Waterfront\Domain\Cart\DTO\Cart;
use Waterfront\Domain\Cart\Services\CartService;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class CartController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CartSerializerFactory $cartSerializerFactory,
        private readonly CartService $cartService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function calculateCart(CartCheckRequest $request): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $serializer = $this->cartSerializerFactory->getPresenter();

        $cart = $serializer->denormalize($request->all(), Cart::class, 'json');
        Assert::isArray($cart->vouchers);

        try {
            $calculatedProductPrices = $this->cartService->checkVouchersAndCalculateAppliedVoucherAppliedAmount(
                $customer,
                $cart,
            );

            $calculatedCart = $this->cartService->convertTotalPriceCollectionToCartWithPricesStructure(
                $calculatedProductPrices,
                $cart,
                $customer,
            );
        } catch (InvalidArgumentException) {
            return new JsonResponse(
                data: ['message' => $this->translator->translate('cart.price.processing-error')],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(
            $this->cartSerializerFactory->getPresenter()->serialize($calculatedCart, 'json'),
            json: true,
        );
    }
}
