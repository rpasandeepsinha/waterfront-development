<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Rules;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class BillingPeriodRules implements DataAwareRule, ValidationRule
{
    /** @var array<mixed> */
    private array $data = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AuthenticationManager $authenticationManager,
        private readonly ProductRepository $productRepository,
        private readonly PriceResolver $priceResolver,
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var string $productSlug */
        $productSlug = Arr::get($this->data, str_replace('billing_period', 'slug', $attribute));
        try {
            $product = $this->productRepository->findProductBySlug($productSlug);
        } catch (ModelNotFoundException) {
            // Slug incorrect: will be caught by different validation rule
            return;
        }

        $contractPeriod = Arr::get($this->data, str_replace('billing_period', 'contract_period', $attribute));
        if ($contractPeriod > 0 && $value > 0 && $value > $contractPeriod) {
            $fail($this->translator->translate('validation.billing_period_exceeds_contract_period'));
            return;
        }

        /** @var string | null $paymentMethod */
        $paymentMethod = Arr::get($this->data, 'paymentMethod');
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $requestPriceType = Arr::get($this->data, str_replace('billing_period', 'status', $attribute));
        assert(is_string($requestPriceType));
        $priceType = ProductPriceType::from($requestPriceType);
        if ($value === 1 && $contractPeriod > 1 && ! $customer->has_direct_debit && $paymentMethod !== null && ! $this->paymentService->isPaymentMethodThatSupportsDirectDebitCreation($paymentMethod)) {
            $productPriceRequest = match ($priceType) {
                ProductPriceType::REGISTRATION => new RegistrationPriceRequest($product),
                ProductPriceType::PROLONGATION => new ProlongationPriceRequest($product),
            };

            $prices = $this->priceResolver->getPriceList(new PriceRequest([$productPriceRequest], $customer));

            // Customer selected multi-month contract with monthly payment but
            // has no direct debit mandate. Did the customer have a choice?
            $isNonMonthlyBillingAvailable = $prices->where('slug', $product->slug)->firstOrFail()->prices->some(
                fn (Price $productPrice) => $productPrice->type === $priceType
                && $productPrice->contractPeriod === $contractPeriod
                && $productPrice->billingPeriod > 1
                && $productPrice->orderable
            );

            $isAddon = ProductAddonCoupling::where('addon_product_id', $product->id)->exists();

            if ($isNonMonthlyBillingAvailable && ! $isAddon) {
                $fail($this->translator->translate('validation.no_monthly_billing_without_direct_debit'));
            }
        }
    }
}
