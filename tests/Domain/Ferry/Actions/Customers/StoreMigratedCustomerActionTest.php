<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Actions\Customers;

use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\DTO\AddressDTO;
use Waterfront\Domain\Customers\DTO\AddressSetDTO;
use Waterfront\Domain\Customers\DTO\ContactDTO;
use Waterfront\Domain\Customers\DTO\ContactSetDTO;
use Waterfront\Domain\Customers\DTO\CustomerDTO;
use Waterfront\Domain\Customers\DTO\DiscountDTO;
use Waterfront\Domain\Customers\DTO\DiscountSetDTO;
use Waterfront\Domain\Customers\DTO\MandateSetDTO;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Exceptions\MandateTypeNotSupportedException;
use Waterfront\Domain\Customers\Models\Customer as CustomerModel;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Ferry\Actions\Customers\StoreMigratedCustomerAction;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Exceptions\ProductPriceNotDiscountableException;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandatePayPalCreateDTO;

#[CoversClass(StoreMigratedCustomerAction::class)]
class StoreMigratedCustomerActionTest extends IntegrationTestCase
{
    private string $customerEmail;

    private Generator $faker;

    private ContactDTO $contact;

    private CustomerDTO $customer;

    private AddressDTO $address;

    private StoreMigratedCustomerAction $storeMigratedCustomerAction;

    private ProductPriceComponent $nlProductProlongationPrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create('en-US');

        $this->customerEmail = 'customer@example.com';

        $this->address = new AddressDTO(
            $this->faker->streetName(),
            $this->faker->buildingNumber(),
            $this->faker->randomLetter(),
            $this->faker->postcode(),
            $this->faker->city(),
            $this->faker->countryCode(),
            $this->faker->word(),
        );

        $this->contact = new ContactDTO(
            firstName: 'John',
            lastName: 'Doe',
            company: null,
            email: 'finance@example.com',
            type: CustomerContactType::FINANCIAL
        );

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $nlProduct = ProductFactory::new()->for($extensionProductGroup)->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $this->nlProductProlongationPrice = new ProductPriceComponentFactory()->for($nlProduct)->prolongation()->createOne([
            'price' => 200,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $this->customer = new CustomerDTO(
            firstName: 'John',
            lastName: 'Doe',
            gender: Gender::MALE,
            email: $this->customerEmail,
            phone: $this->faker->e164PhoneNumber(),
            language: Locale::DUTCH,
            addresses: new AddressSetDTO($this->address),
            contacts: new ContactSetDTO($this->contact),
            department: $this->faker->text(),
            organization: $this->faker->text(),
            cocNumber: $this->faker->text(),
            vatNumber: $this->faker->text(),
            creditLimit: $this->faker->randomNumber(),
            purchaseReference: $this->faker->text(),
            paymentTerms: $this->faker->numberBetween(1, 12),
            buName: $this->faker->text(),
            buCustomerNumber: 'bu-customer-number',
            groupType: $this->faker->text(),
            paymentType: PaymentType::DIRECT,
            internalNote: $this->faker->text(),
            walletCreditBalance: 1,
            discounts: new DiscountSetDTO(),
            productGroupDiscounts: [],
            mandates: new MandateSetDTO(),
            dnsTemplates: [],
            labels: [],
            customerSince: null,
        );

        $this->storeMigratedCustomerAction = self::resolve(StoreMigratedCustomerAction::class);
    }

    #[Test]
    public function execute(): void
    {
        // Seed random customer, so we get a different id/customer_number
        $existingCustomer = CustomerFactory::new()->createOne();

        $customerInfo = $this->storeMigratedCustomerAction->execute($this->customer);

        $customer = CustomerModel::query()->where('email', $this->customer->email)->firstOrFail();

        self::assertSame(
            [
                'customerId' => $customer->id,
                'customerNumber' => $existingCustomer->customer_number + 1,
                'referenceCustomerId' => 'bu-customer-number',
            ],
            $customerInfo
        );

        self::assertDatabaseHas('customers', ['email' => $this->customerEmail]);
        self::assertDatabaseCount('product_price_components', 1);
        self::assertDatabaseHas('product_price_components', ['price' => 200]);
    }

    #[Test]
    public function executeWithDiscount(): void
    {
        $discount = new DiscountDTO(
            price: 100,
            contractPeriod: 12,
            billingPeriod: 12,
            baseProductProlongationPrice: Price::fromPrice($this->nlProductProlongationPrice),
        );

        $discountCustomer = new CustomerDTO(
            firstName: 'John',
            lastName: 'Doe',
            gender: Gender::MALE,
            email: $this->customerEmail,
            phone: $this->faker->e164PhoneNumber(),
            language: Locale::DUTCH,
            addresses: new AddressSetDTO($this->address),
            contacts: new ContactSetDTO($this->contact),
            department: $this->faker->text(),
            organization: $this->faker->text(),
            cocNumber: $this->faker->text(),
            vatNumber: $this->faker->text(),
            creditLimit: $this->faker->randomNumber(),
            purchaseReference: $this->faker->text(),
            paymentTerms: $this->faker->numberBetween(1, 12),
            buName: $this->faker->text(),
            buCustomerNumber: $this->faker->text(),
            groupType: $this->faker->text(),
            paymentType: PaymentType::DIRECT,
            internalNote: $this->faker->text(),
            walletCreditBalance: 1,
            discounts: new DiscountSetDTO($discount),
            productGroupDiscounts: [],
            mandates: new MandateSetDTO(),
            dnsTemplates: [],
            labels: [],
            customerSince: null,
        );

        $this->storeMigratedCustomerAction->execute($discountCustomer);

        self::assertDatabaseHas('customers', ['email' => $this->customerEmail]);
        self::assertDatabaseCount('product_price_components', 2);
        self::assertDatabaseCount('product_discounts', 1);
        self::assertDatabaseHas('product_price_components', ['price' => 100, 'type' => PriceComponentType::PROLONGATION_STAFFEL->value]);
    }

    #[Test]
    public function executeWithWrongDiscountPeriodThrowsException(): void
    {
        $discount = new DiscountDTO(
            price: 100,
            contractPeriod: 12,
            billingPeriod: 3,
            baseProductProlongationPrice: Price::fromPrice($this->nlProductProlongationPrice),
        );

        $discountCustomer = new CustomerDTO(
            firstName: 'John',
            lastName: 'Doe',
            gender: Gender::MALE,
            email: $this->customerEmail,
            phone: $this->faker->e164PhoneNumber(),
            language: Locale::DUTCH,
            addresses: new AddressSetDTO($this->address),
            contacts: new ContactSetDTO($this->contact),
            department: $this->faker->text(),
            organization: $this->faker->text(),
            cocNumber: $this->faker->text(),
            vatNumber: $this->faker->text(),
            creditLimit: $this->faker->randomNumber(),
            purchaseReference: $this->faker->text(),
            paymentTerms: $this->faker->numberBetween(1, 12),
            buName: $this->faker->text(),
            buCustomerNumber: $this->faker->text(),
            groupType: $this->faker->text(),
            paymentType: PaymentType::DIRECT,
            internalNote: $this->faker->text(),
            walletCreditBalance: 1,
            discounts: new DiscountSetDTO($discount),
            productGroupDiscounts: [],
            mandates: new MandateSetDTO(),
            dnsTemplates: [],
            labels: [],
            customerSince: null,
        );

        $this->expectException(ProductPriceNotDiscountableException::class);

        $this->storeMigratedCustomerAction->execute($discountCustomer);
    }

    #[Test]
    public function retryAbility(): void
    {
        $customerModel = CustomerFactory::new()
            ->createOne([
                'email' => $this->customer->email,
            ]);

        $migratedCustomer = MigratedCustomersFactory::new()
            ->createOne([
                'reference_customer_number' => $this->customer->buCustomerNumber,
                'reference_name' => $this->customer->buName,
                'group_type' => $this->customer->groupType,
                'administrative_successful' => false,
                'successful' => false,
            ]);

        $customerModel->migratedCustomers()->attach($migratedCustomer);

        self::assertDatabaseCount(CustomerModel::class, 1);
        self::assertDatabaseCount(CustomerContact::class, 0);
        self::assertDatabaseCount(CustomerAddress::class, 0);

        $this->storeMigratedCustomerAction->execute($this->customer);

        self::assertSame(1, CustomerModel::query()->where('email', $this->customer->email)->count());
        self::assertSame(1, CustomerContact::query()->where('email', $this->contact->email)->count());
        self::assertSame(1, CustomerAddress::query()->where('customer_id', $customerModel->id)->count());
    }

    #[Test]
    public function mandateTypeNotSupportedExceptionIsThrown(): void
    {
        // PayPal isn't supported yet, so it should throw an exception
        $customerWithMandate = new CustomerDTO(
            firstName: 'John',
            lastName: 'Doe',
            gender: Gender::MALE,
            email: $this->customerEmail,
            phone: $this->faker->e164PhoneNumber(),
            language: Locale::DUTCH,
            addresses: new AddressSetDTO($this->address),
            contacts: new ContactSetDTO($this->contact),
            department: $this->faker->text(),
            organization: $this->faker->text(),
            cocNumber: $this->faker->text(),
            vatNumber: $this->faker->text(),
            creditLimit: $this->faker->randomNumber(),
            purchaseReference: $this->faker->text(),
            paymentTerms: $this->faker->numberBetween(1, 12),
            buName: $this->faker->text(),
            buCustomerNumber: $this->faker->text(),
            groupType: $this->faker->text(),
            paymentType: PaymentType::DIRECT,
            internalNote: $this->faker->text(),
            walletCreditBalance: 1,
            discounts: new DiscountSetDTO(),
            productGroupDiscounts: [],
            mandates: new MandateSetDTO(
                new MollieMandatePayPalCreateDTO(
                    consumerName: 'P. Pal',
                    consumerEmail: 'email@testing.test',
                    paypalBillingAgreementId: 'billingAgree1',
                    signatureDate: '2023-09-06',
                    mandateReference: 'pp1'
                )
            ),
            dnsTemplates: [],
            labels: [],
            customerSince: null,
        );

        self::expectException(MandateTypeNotSupportedException::class);
        self::expectExceptionMessageIs("Mandate type 'paypal' not supported");

        $this->storeMigratedCustomerAction->execute($customerWithMandate);
    }
}
