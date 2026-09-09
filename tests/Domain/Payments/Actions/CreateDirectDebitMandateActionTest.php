<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Actions\CreateDirectDebitMandateAction;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;

#[CoversClass(CreateDirectDebitMandateAction::class)]
class CreateDirectDebitMandateActionTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->customer = new CustomerFactory()->createOne();
        $this->customer->customer_number = 10001234;
        $this->customer->save();
    }

    #[Test]
    public function execute(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers' =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    return Http::response(include __DIR__ . '/data/create_customer_response.php', 201);
                },

            'api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/mandates' =>
                function (Request $request) {
                    if ($request->method() === 'GET') {
                        return Http::response(include __DIR__ . '/data/list_mandates_no_mandates_response.php');
                    }

                    if ($request->method() === 'POST') {
                        return Http::response(include __DIR__ . '/data/create_mollie_mandate_directdebit_response.php');
                    }
                },

            'api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa/mandates/mdt_h3gAaD5zP' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/get_mollie_mandate_response.php');
                },

            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=10001234' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(['data' => []]);
                },

            'api.paytsoftware.test/v1/psp_mandates' =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    return Http::response(include __DIR__ . '/data/create_payt_mandate_response.php', 201);
                },
        ]);

        $action = self::resolve(CreateDirectDebitMandateAction::class);

        $mandate = $action->execute(
            customer: $this->customer,
            consumerName: 'Consumer name',
            consumerAccount: 'NL18RABO0123459876',
            signatureDate: CarbonImmutable::createFromDate(2018, 5, 8)->toImmutable(),
            consumerBic: 'RABONL2U',
        );

        self::assertSame(1, MollieCustomer::query()->count());
        self::assertSame(1, Mandate::query()->count());

        self::assertSame('cst_kEn1PlbGa', $mandate->mollieCustomer->mollie_customer_reference_id);
        self::assertSame('mdt_h3gAaD5zP', $mandate->mollie_mandate_reference_id);
        self::assertSame('5678', $mandate->payt_mandate_reference_id);
        self::assertTrue($this->customer->has_direct_debit);
    }
}
