<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Invoice;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Domain\Harbor\Util\Traits\CreditFlow\CustomerTrait;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceBatchCrediter\InvoiceToCreditBatch;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceBatchCrediter;

#[CoversClass(InvoiceBatchCrediter::class)]
class InvoiceBatchCrediterTest extends IntegrationTestCase
{
    use CustomerTrait;

    /**
     * TODO https://yh-jira.atlassian.net/browse/WATER-3792 so that we can dynamically test new Invoices.
     *
     *
     * @param array<array<string, mixed>> $invoicesToCreditData
     */
    #[DataProvider('batchCreditDataProvider')]
    #[Test]
    public function batchCredit(
        array $invoicesToCreditData,
    ): void {
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
        $invoicesToCredit = [];

        // Create the invoices.
        foreach ($invoicesToCreditData as $invoiceToCreditData) {
            /** @var int $originalInvoicePrice */
            $originalInvoicePrice = $invoiceToCreditData['originalInvoicePrice'];
            /** @var int $amountToCredit */
            $amountToCredit = $invoiceToCreditData['amountToCredit'];
            /** @var bool $shouldCreateNewInvoice */
            $shouldCreateNewInvoice = $invoiceToCreditData['shouldCreateNewInvoice'];

            $originalInvoice = new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date' => CarbonImmutable::now(),
                    'end_date' => CarbonImmutable::now()->addYear(),
                    'net_price' => $originalInvoicePrice,
                    'gross_price' => $originalInvoicePrice,
                ]);

            $invoicesToCredit[] = new InvoiceToCredit($originalInvoice, $amountToCredit, $shouldCreateNewInvoice);
        }

        $invoiceToCreditBatch = new InvoiceToCreditBatch($invoicesToCredit);
        $batchInvoiceCreditResult = self::resolve(InvoiceBatchCrediter::class)->batchCredit($invoiceToCreditBatch);

        /**
         * TESTING.
         */
        self::assertSameSize($invoicesToCreditData, $batchInvoiceCreditResult->getCreditInvoices());
        $expectedNewInvoiceCount = array_reduce(
            $invoicesToCreditData,
            function (int $expectedNewInvoiceCount, array $invoiceToCreditData): int {
                /** @var bool $shouldCreateNewInvoice */
                $shouldCreateNewInvoice = $invoiceToCreditData['shouldCreateNewInvoice'];

                return $shouldCreateNewInvoice ? $expectedNewInvoiceCount + 1 : $expectedNewInvoiceCount;
            },
            0,
        );

        // TODO: Remove this check when the ticket is resolved and make a test that expects multiple new invoices.
        if ($expectedNewInvoiceCount > 1) {
            throw new InvalidArgumentException(
                'Test does not support multiple new Invoices. See https://yh-jira.atlassian.net/browse/WATER-3792.',
            );
        }

        self::assertCount(
            $expectedNewInvoiceCount,
            $batchInvoiceCreditResult->getNewInvoices(),
            sprintf(
                '%d Invoices were marked as requiring a new Invoice, but batch credit result only shows %d.',
                $expectedNewInvoiceCount,
                count($batchInvoiceCreditResult->getNewInvoices()),
            ),
        );

        /**
         * @var array<string, mixed> $invoiceToCreditData
         */
        foreach ($invoicesToCreditData as $invoiceToCreditData) {
            /** @var int $originalInvoicePrice */
            $originalInvoicePrice = $invoiceToCreditData['originalInvoicePrice'];
            /** @var int $amountToCredit */
            $amountToCredit = $invoiceToCreditData['amountToCredit'];
            /** @var bool $shouldCreateNewInvoice */
            $shouldCreateNewInvoice = $invoiceToCreditData['shouldCreateNewInvoice'];

            // No way of retrieving the new Invoice at the moment, so we only support testing one. See the ticket above.
            $newInvoice = $shouldCreateNewInvoice ? $batchInvoiceCreditResult->getNewInvoices()[0] ?? null : null;

            // Partial credit -> new invoices are not supported.
            if ($amountToCredit !== $originalInvoicePrice) {
                self::assertNull($newInvoice, 'Crediting partially and then expecting a new Invoice is not supported.');
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function batchCreditDataProvider(): array
    {
        return [
            'successfully credits all given invoices' => [
                'invoicesToCreditData' => [
                    [
                        'originalInvoicePrice' => 200,
                        'amountToCredit' => 200,
                        'shouldCreateNewInvoice' => true,
                    ],
                    [
                        'originalInvoicePrice' => 12345,
                        'amountToCredit' => 12345,
                        'shouldCreateNewInvoice' => false,
                    ],
                    [
                        'originalInvoicePrice' => 653453,
                        'amountToCredit' => 12342,
                        'shouldCreateNewInvoice' => false,
                    ],
                ],
            ],
        ];
    }
}
