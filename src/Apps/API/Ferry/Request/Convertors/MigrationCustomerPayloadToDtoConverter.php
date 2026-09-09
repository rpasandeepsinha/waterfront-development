<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Convertors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Symfony\Component\Serializer\Serializer;
use UnexpectedValueException;
use Waterfront\Domain\Customers\DTO\AddressSetDTO;
use Waterfront\Domain\Customers\DTO\ContactSetDTO;
use Waterfront\Domain\Customers\DTO\CustomerDTO;
use Waterfront\Domain\Customers\DTO\DiscountSetDTO;
use Waterfront\Domain\Customers\DTO\DnsTemplateDTO;
use Waterfront\Domain\Customers\DTO\MandateSetDTO;
use Waterfront\Domain\Customers\DTO\ProductGroupDiscountDTO;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;

class MigrationCustomerPayloadToDtoConverter
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly PriceResolver $priceResolver,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();
    }

    /**
     * @param array<mixed> $customerPayload
     */
    public function convert(array $customerPayload): CustomerDTO
    {
        /** @var array<int, array<string, string>> $addresses */
        $addresses = Arr::get($customerPayload, 'addresses', []);
        /** @var array<int, string> $contacts */
        $contacts = Arr::get($customerPayload, 'contacts', []);
        /** @var string $gender */
        $gender = Arr::get($customerPayload, 'gender', Gender::NEUTRAL->value);
        /** @var string $customerLocale */
        $customerLocale = Arr::get($customerPayload, 'language', Locale::DUTCH->value);

        /** @var array<int, array{slug: string, contract_period: int, billing_period: int, price: int}> $productsDiscounts */
        $productsDiscounts = Arr::get($customerPayload, 'products_discounts', []);
        $productsDiscountsWithProducts = $this->mapProducts($productsDiscounts);

        /** @var array<int, ProductGroupDiscountDTO> $productGroupDiscounts */
        $productGroupDiscounts = $this->serializer->denormalize(
            Arr::get($customerPayload, 'product_group_discounts', []),
            ProductGroupDiscountDTO::class . '[]'
        );

        /** @var array<int, array<string, string>> $mandates */
        $mandates = Arr::get($customerPayload, 'mandates', []) ?? [];
        /** @var array<int, non-empty-string> $labels */
        $labels = Arr::get($customerPayload, 'labels', []) ?? [];

        /** @var array<int, DnsTemplateDTO> $dnsTemplates */
        $dnsTemplates = $this->serializer->denormalize(
            Arr::get($customerPayload, 'dnsTemplates', []),
            DnsTemplateDTO::class . '[]'
        );

        return new CustomerDTO(
            firstName: $this->getAsString($customerPayload, 'firstName'),
            lastName: $this->getAsString($customerPayload, 'lastName'),
            gender: Gender::from($gender),
            email: $this->getAsString($customerPayload, 'email'),
            phone: $this->getAsString($customerPayload, 'phone'),
            language: Locale::from($customerLocale),
            addresses: AddressSetDTO::fromArray($addresses),
            contacts: ContactSetDTO::fromArray($contacts),
            department: $this->getAsStringOrNull($customerPayload, 'department'),
            organization: $this->getAsStringOrNull($customerPayload, 'organization'),
            cocNumber: $this->getAsStringOrNull($customerPayload, 'cocNumber'),
            vatNumber: $this->getAsStringOrNull($customerPayload, 'vatNumber'),
            creditLimit: $this->getAsInteger($customerPayload, 'creditLimit'),
            purchaseReference: $this->getAsStringOrNull($customerPayload, 'purchaseReference'),
            paymentTerms: $this->getAsInteger($customerPayload, 'paymentTerms'),
            buName: $this->getAsString($customerPayload, 'referenceName'),
            buCustomerNumber: $this->getAsString($customerPayload, 'referenceCustomerId'),
            groupType: $this->getAsString($customerPayload, 'groupType'),
            paymentType: $this->getAsBoolean($customerPayload, 'validated') ? PaymentType::CREDIT : PaymentType::DIRECT,
            internalNote: $this->getAsStringOrNull($customerPayload, 'internalNote'),
            walletCreditBalance: $this->getAsIntegerOrNull($customerPayload, 'wallet_credit_balance'),
            discounts: DiscountSetDTO::fromArray($productsDiscountsWithProducts, $this->getPriceList($productsDiscountsWithProducts)),
            productGroupDiscounts: $productGroupDiscounts,
            mandates: MandateSetDTO::fromArray($mandates),
            dnsTemplates: $dnsTemplates,
            labels: $labels,
            customerSince: new CarbonImmutable($this->getAsStringOrNull($customerPayload, 'customerSince'))
        );
    }

    /**
     * @param array<int, array{slug: string, contract_period: int, billing_period: int, price: int}> $productsDiscounts
     *
     * @return array<int, array{product: Product, slug: string, contract_period: int, billing_period: int, price: int}>
     */
    private function mapProducts(array $productsDiscounts): array
    {
        foreach ($productsDiscounts as &$productDiscount) {
            $productDiscount['product'] = $this->productRepository->findProductBySlug($productDiscount['slug']);
        }

        /** @var array<int, array{product: Product, slug: string, contract_period: int, billing_period: int, price: int}> $productsDiscounts */
        return $productsDiscounts;
    }

    /**
     * @param array<int, array{product: Product, slug: string, contract_period: int, billing_period: int, price: int}> $productDiscounts
     */
    private function getPriceList(array $productDiscounts): PriceList
    {
        $products = array_column($productDiscounts, 'product');
        $productPriceRequests = array_map(fn ($product) => new ProlongationPriceRequest($product), $products);

        return $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, null));
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsStringOrNull(array $payload, string $key): string|null
    {
        /** @var string|null $value */
        $value = Arr::get($payload, $key);

        if ($value === '') {
            throw new UnexpectedValueException(
                sprintf(
                    'Value for %s needs to be a non-empty string or null',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsString(array $payload, string $key): string
    {
        /** @var string|null $value */
        $value = Arr::get($payload, $key);

        // Can still be null if the key exists
        if ($value === null || $value === '') {
            throw new UnexpectedValueException(
                sprintf(
                    'Value for %s needs to be a non-empty string',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsIntegerOrNull(array $payload, string $key): int|null
    {
        /** @var string|null $value */
        $value = Arr::get($payload, $key);

        if (! is_numeric($value) && ! is_null($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Value for %s needs to be a number or null',
                    $key
                )
            );
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsInteger(array $payload, string $key): int
    {
        $value = Arr::get($payload, $key);

        if (! is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Value for %s needs to be a number',
                    $key
                )
            );
        }

        return (int) $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsBoolean(array $payload, string $key): bool
    {
        return (bool) Arr::get($payload, $key, false);
    }
}
