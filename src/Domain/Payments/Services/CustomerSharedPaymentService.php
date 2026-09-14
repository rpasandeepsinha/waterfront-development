<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Routing\UrlGenerator;
use Psr\Log\LoggerInterface;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Payments\Exceptions\PaymentException;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Config\ApplicationConfig;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\Tenant;

class CustomerSharedPaymentService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly LoggerInterface $logger,
        private readonly ApplicationConfig $applicationConfig,
        private readonly AuthenticationManager $authenticationManager,
        private readonly UrlGenerator $router,
        private readonly ConfigurationInterface $configuration,
        private readonly OrderService $orderService,
        private readonly Environment $environment,
        private readonly Tenant $tenant,
    ) {
    }

    /**
     * Create a payment for a customer.
     *
     * @throws PaymentException
     */
    public function createPayment(
        Customer $customer,
        PaymentParameters $parameters,
        ?bool $createDirectDebitMandate = null,
    ): Payment {
        try {
            $this->logger->info(
                self::class . '::createPayment - Creating payment',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    LoggingContextKeys::META => [
                        'parameters' => $parameters->toArray(),
                    ],
                ],
            );

            return $this->payments->createPayment($customer, $parameters, $createDirectDebitMandate);
        } catch (PaymentException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Attempts to approve a customer by checking the status of a payment.
     *
     * @throws PaymentException
     */
    public function attemptCustomerApproval(string $externalId): void
    {
        try {
            $this->logger->info(
                self::class . '::attemptCustomerApproval - Syncing payment',
                [
                    LoggingContextKeys::META => [
                        'external_id' => $externalId,
                    ],
                ],
            );

            $this->payments->syncPayment($externalId);
        } catch (PaymentException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
            throw $exception;
        }
    }

    /**
     * @throws AuthenticationException
     */
    public function checkoutUrlForCart(
        Order $order,
        ?string $paymentMethod = null,
        ?bool $createDirectDebitMandate = null,
    ): ?string {
        $totalPrice = $order->total_price;

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        if ($customer->payment_type === PaymentType::DIRECT) {
            if ($customer->vat_rate !== null) {
                $totalPrice = $totalPrice * (1 + ($customer->vat_rate / 100));
            } else {
                $totalPrice = $this->priceWithTax($totalPrice);
            }
        }

        $parameters = new PaymentParameters(
            currency: 'EUR',
            amount: $this->correctPrice($totalPrice),
            description: 'Bestelling ' . $order->id . ' - K' . $customer->customer_number,
            redirectUrl: $this->getRedirectUrl() . '?order=' . $order->id,
            webhookUrl: $this->getWebhookUrl(),
            method: $paymentMethod,
            metaData: ['order' => $order->id],
        );

        try {
            $payment = $this->createPayment($order->customer, $parameters, $createDirectDebitMandate);
        } catch (PaymentException) {
            return null;
        }

        return $payment->checkout_url;
    }

    /**
     * Converts price into the format Mollie wants. We use string operations to avoid rounding errors.
     */
    public function correctPrice(float $price): string
    {
        return number_format(round($price / 100, 2), 2, '.', '');
    }

    public function isPaymentMethodThatSupportsDirectDebitCreation(string $paymentMethod): bool
    {
        return $this->payments->isPaymentMethodThatSupportsDirectDebitCreation($paymentMethod);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function requiresDirectPayment(Customer $customer, Order $order, string $orderPaymentMethod): bool
    {
        if ($order->total_price === 0) {
            return false;
        }

        if ($customer->payment_type === PaymentType::CREDIT) {
            return false;
        }

        if ($orderPaymentMethod === 'credit') {
            // If the order is an upgrade order or an add-ons only order we do NOT require direct payment
            if ($this->orderService->orderContainsOnlyUpgradesOrAddonsOrMutations($order)) {
                return false;
            }

            if (
                $this->authenticationManager->getAuthenticatedSubject()->identitySchema->schemaId !== SchemaId::EMPLOYEE
            ) {
                throw new AuthorizationException('You are not authorized to order on credit');
            }

            return false;
        }

        return true;
    }

    public function getRedirectUrl(): string
    {
        return sprintf('%s/processing-payment', $this->getShopUrl());
    }

    public function getConfirmationUrl(): string
    {
        return sprintf('%s/thank-you', $this->getShopUrl());
    }

    private function getWebhookUrl(): string
    {
        $clientId = $this->configuration->getAsString('app.storefront.webhook_hydra_client_id');
        $clientSecret = $this->configuration->getAsString('app.storefront.webhook_hydra_client_secret');
        $baseUrl = $this->configuration->getAsString('app.url_webhook');

        $route = $this->router->route('storefront.payment.webhook');
        if (str_starts_with($route, 'http')) {
            $route = parse_url($route, PHP_URL_PATH);
        }

        assert(is_string($route));

        return $baseUrl . '/' . ltrim($route, '/') . '?auth=' . base64_encode($clientId . ':' . $clientSecret);
    }

    private function getShopUrl(): string
    {
        return match ($this->environment) {
            Environment::PROD => match ($this->tenant) {
                Tenant::VERSIO => 'https://shop.mijn.versio.nl',
                Tenant::YOURHOSTING => 'https://shop.account.yourhosting.nl',
            },
            Environment::UAT => match ($this->tenant) {
                Tenant::VERSIO => 'https://shop.versio.sandwave.review',
                Tenant::YOURHOSTING => 'https://shop.yourhosting.sandwave.review',
            },
            Environment::SIT, Environment::DEV, Environment::TST => 'https://atlantis.sandwaveio.dev',
        };
    }

    private function taxPercentage(): float
    {
        return 1 + ($this->applicationConfig->defaultTaxRate / 100);
    }

    private function priceWithTax(float $priceWithoutTax): float
    {
        return $priceWithoutTax * $this->taxPercentage();
    }
}
