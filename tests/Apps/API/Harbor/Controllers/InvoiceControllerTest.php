<?php

declare(strict_types=1);

namespace Tests\Apps\API\Harbor\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use stdClass;
use Tests\Domain\Harbor\Util\Traits\CreditFlow\CustomerTrait;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Harbor\Controllers\InvoiceController;
use Waterfront\Domain\Invoices\Models\Invoice;

#[CoversClass(InvoiceController::class)]
class InvoiceControllerTest extends IntegrationTestCase
{
    use CustomerTrait;

    #[Test]
    public function creditRequestWithoutCorrectHarborApiKeyReturnsHttpForbidden(): void
    {
        $response = $this->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => [
                    [
                        'waterfrontInvoiceId' => 1,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => false,
                        'creditReason' => null,
                    ],
                ],
            ],
        );
        $response->assertUnauthorized();
    }

    #[Test]
    public function creditRequestReturnsHttpFoundResponseWhenNoDataIsGiven(): void
    {
        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
        );
        $response->assertUnprocessable();

        $exception = $response->exception;
        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame('Dit veld is verplicht.', $exception->getMessage());

        $errors = $exception->validator->errors()->messages();
        self::assertCount(1, $errors);

        $errorArray = reset($errors);
        self::assertIsArray($errorArray);
        $error = reset($errorArray);
        self::assertIsString($error);
        self::assertSame('Dit veld is verplicht.', $error);
    }

    #[Test]
    public function creditRequestReturnsHttpFoundResponseWhenIncorrectWaterfrontInvoiceIdTypeIsGiven(): void
    {
        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => [
                    [
                        'waterfrontInvoiceId' => true,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => 1,
                        'creditReason' => null,
                    ],
                ],
            ],
        );

        $response->assertUnprocessable();
        $exception = $response->exception;
        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame('Dit veld dient een nummer te zijn.', $exception->getMessage());

        $errors = $exception->validator->errors()->messages();
        self::assertCount(1, $errors);

        $errorArray = reset($errors);
        self::assertIsArray($errorArray);
        $error = reset($errorArray);
        self::assertIsString($error);
        self::assertSame('Dit veld dient een nummer te zijn.', $error);
    }

    #[Test]
    public function creditRequestReturnsHttpFoundResponseWhenGivenInvoiceIdDoesNotExist(): void
    {
        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => [
                    [
                        'waterfrontInvoiceId' => 1,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => false,
                        'creditReason' => null,
                    ],
                ],
            ],
        );

        $response->assertUnprocessable();
        $exception = $response->exception;
        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame('Invoice id 1 does not exist in Waterfront.', $exception->getMessage());

        $errors = $exception->validator->errors()->messages();
        self::assertCount(1, $errors);

        $errorArray = reset($errors);
        self::assertIsArray($errorArray);
        $error = reset($errorArray);
        self::assertIsString($error);
        self::assertSame('Invoice id 1 does not exist in Waterfront.', $error);
    }

    #[Test]
    public function creditRequestReturnsHttpFoundResponseWhenIncorrectShouldCreateNewInvoiceTypeIsGiven(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoice = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->createOne();

        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => [
                    [
                        'waterfrontInvoiceId' => $invoice->id,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => new stdClass(),
                        'creditReason' => $invoice->credit_reason,
                    ],
                ],
            ],
        );

        $response->assertUnprocessable();
        $exception = $response->exception;
        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame('Dit veld kan enkel true of false zijn.', $exception->getMessage());

        $errors = $exception->validator->errors()->messages();
        self::assertCount(1, $errors);

        $errorArray = reset($errors);
        self::assertIsArray($errorArray);
        $error = reset($errorArray);
        self::assertIsString($error);
        self::assertSame('Dit veld kan enkel true of false zijn.', $error);
    }

    #[Test]
    public function creditRequestWith0AmountToCreditSucceeds(): void
    {
        $customer = $this->createCreditFlowCustomerWithAddress();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => $productGroup->slug,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
            ]);
        $invoices = array_map(
            fn (): Invoice => new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date' => CarbonImmutable::now(),
                    'end_date' => CarbonImmutable::now()->addYear(),
                    'gross_price' => 0,
                    'net_price' => 0,
                ]),
            range(0, 2),
        );

        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => array_map(
                    fn (Invoice $invoice): array => [
                        'waterfrontInvoiceId' => $invoice->id,
                        'amountToCredit' => 0,
                        'shouldCreateNewInvoice' => ($invoice->id % 2) === 0,
                        'creditReason' => $invoice->credit_reason,
                    ],
                    $invoices,
                ),
            ],
        );

        self::assertNull($response->exception);
    }

    #[Test]
    public function creditRequestWithApiKeyAndCorrectlyStructuredDataReturnsResponse(): void
    {
        $customer = $this->createCreditFlowCustomerWithAddress();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => $productGroup->slug,
        ]);
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
            ]);
        $invoices = array_map(
            fn (): Invoice => new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date' => CarbonImmutable::now(),
                    'end_date' => CarbonImmutable::now()->addYear(),
                    'ledger_code' => $productGroup->ledger_code,
                    'credit_reason' => InvoiceLineCreditReason::REASON_OTHER,
                ]),
            range(0, 2),
        );

        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => array_map(
                    fn (Invoice $invoice): array => [
                        'waterfrontInvoiceId' => $invoice->id,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => ($invoice->id % 2) === 0,
                        'creditReason' => $invoice->credit_reason?->value,
                    ],
                    $invoices,
                ),
            ],
        );

        self::assertNull($response->exception);

        // Testing the data validity is done in InvoiceCrediterTest.
    }

    #[Test]
    public function creditRequestWithASoftDeletedProductReturnsResponse(): void
    {
        $customer = $this->createCreditFlowCustomerWithAddress();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => $productGroup->slug,
        ]);
        $product->delete();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
            ]);
        $invoices = array_map(
            fn (): Invoice => new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date' => CarbonImmutable::now(),
                    'end_date' => CarbonImmutable::now()->addYear(),
                    'ledger_code' => $productGroup->ledger_code,
                    'credit_reason' => InvoiceLineCreditReason::REASON_OTHER,
                ]),
            range(0, 2),
        );

        $response = $this->actingAsSystem()->post(
            $this->generateRoute('harbor.invoice.credit'),
            [
                'invoicesToCredit' => array_map(
                    fn (Invoice $invoice): array => [
                        'waterfrontInvoiceId' => $invoice->id,
                        'amountToCredit' => 1,
                        'shouldCreateNewInvoice' => ($invoice->id % 2) === 0,
                        'creditReason' => $invoice->credit_reason?->value,
                    ],
                    $invoices,
                ),
            ],
        );

        self::assertNull($response->exception);

        // Testing the data validity is done in InvoiceCrediterTest.
    }
}
