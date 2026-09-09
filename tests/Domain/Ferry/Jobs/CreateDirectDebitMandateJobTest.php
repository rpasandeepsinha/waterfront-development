<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Jobs\CreateDirectDebitMandateJob;
use Waterfront\Domain\Payments\Actions\CreateDirectDebitMandateAction;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;

#[CoversClass(CreateDirectDebitMandateJob::class)]
class CreateDirectDebitMandateJobTest extends IntegrationTestCase
{
    private Customer $customer;

    private MigratedCustomer $migratedCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerFactory::new()->createOne();

        $this->customer->migratedCustomers()->save(MigratedCustomersFactory::new()->makeOne([
            'reference_customer_number' => 'reference12345',
            'reference_name' => 'testBU',
        ]));

        $this->migratedCustomer = $this->customer->migratedCustomers->firstOrFail();
    }

    #[Test]
    public function createDirectDebitMandateJobSuccess(): void
    {
        $mock = self::createStub(CreateDirectDebitMandateAction::class);

        $mock->method('execute')
            ->willReturnCallback(function (): Mandate {
                $mollieCustomer = new MollieCustomer();
                $mollieCustomer->mollie_customer_reference_id = 'cst_1';

                $this->customer->mollieCustomer()->save($mollieCustomer);

                $mandate = new Mandate();
                $mandate->mollie_mandate_reference_id = 'mdt_1';
                $mandate->payt_mandate_reference_id = '5';
                $mandate->signature_date = CarbonImmutable::createFromDate(2023, 8, 31)->toImmutable();
                $mandate->method = MollieMandateMethod::DIRECTDEBIT;

                $mollieCustomer->mandates()->save($mandate);

                return $mandate->refresh();
            });

        $logger = self::resolve(LoggerInterface::class);
        $dispatcher = self::resolve(Dispatcher::class);

        $mollieMandateCreateDTO = new MollieMandateDirectDebitCreateDTO(
            consumerName: 'John Doe',
            consumerAccount: 'NL18RABO0123459876',
            signatureDate: '2023-08-31',
            consumerBic: 'RABONL2U',
        );

        $job = new CreateDirectDebitMandateJob(
            customer: $this->customer,
            mollieMandateDirectDebitCreateDTO: $mollieMandateCreateDTO,
            migratedCustomer: $this->migratedCustomer
        );

        $job->handle($mock, $logger, $dispatcher);

        $this->migratedCustomer->refresh();

        $coupledMandate = $this->migratedCustomer->mandates()->first();

        self::assertInstanceOf(Mandate::class, $coupledMandate);

        self::assertSame('mdt_1', $coupledMandate->mollie_mandate_reference_id);
        self::assertSame('5', $coupledMandate->payt_mandate_reference_id);
    }

    #[Test]
    public function createDirectDebitMandateJobFail(): void
    {
        Http::fake([
            'ferry.azurewebsites.net//api/ConsumeFerryResponse' =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    $data = $request->data();

                    /** @var string|int $error */
                    $error = Arr::get($data, 'data.error');

                    self::assertSame('direct_debit_creation_unsuccessful', Arr::get($data, 'type'));
                    self::assertSame('reference12345', Arr::get($data, 'data.reference_customer_number'));
                    self::assertSame('testBU', Arr::get($data, 'data.reference_name'));
                    self::assertStringContainsString('Mollie API exception', (string) $error);

                    return Http::response();
                },
        ]);

        $mock = self::createStub(CreateDirectDebitMandateAction::class);

        $mollieCustomer = new MollieCustomer();
        $mollieCustomer->id = 5;

        $mandate = new Mandate();
        $mandate->id = 10;
        $mandate->payt_mandate_reference_id = '5';
        $mandate->signature_date = CarbonImmutable::createFromDate(2023, 8, 31)->toImmutable();
        $mandate->method = MollieMandateMethod::DIRECTDEBIT;
        $mandate->mollieCustomer = $mollieCustomer;

        $mock->method('execute')
            ->willThrowException(
                new MollieMandateApiException(
                    422,
                    'error',
                    'detail',
                    null
                )
            );

        $logger = self::resolve(LoggerInterface::class);
        $dispatcher = self::resolve(Dispatcher::class);

        $mollieMandateCreateDTO = new MollieMandateDirectDebitCreateDTO(
            consumerName: 'John Doe',
            consumerAccount: 'NL18RABO0123459876',
            signatureDate: '2023-08-31',
            consumerBic: 'RABONL2U',
        );

        $job = new CreateDirectDebitMandateJob(
            customer: $this->customer,
            mollieMandateDirectDebitCreateDTO: $mollieMandateCreateDTO,
            migratedCustomer: $this->migratedCustomer
        );

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionMessageIs('Mollie API exception, code: 422, title: error, detail: detail, field: ');

        $job->handle($mock, $logger, $dispatcher);
    }

    #[Test]
    public function createDirectDebitMandateJobBicRetry(): void
    {
        $mollieCustomer = new MollieCustomer();
        $mollieCustomer->id = 5;

        $mandate = new Mandate();
        $mandate->id = 10;
        $mandate->payt_mandate_reference_id = '5';
        $mandate->signature_date = CarbonImmutable::createFromDate(2023, 8, 31)->toImmutable();
        $mandate->method = MollieMandateMethod::DIRECTDEBIT;
        $mandate->mollieCustomer = $mollieCustomer;

        $mollieMandateCreateDTO = new MollieMandateDirectDebitCreateDTO(
            consumerName: 'John Doe',
            consumerAccount: 'NL18RABO0123459876',
            signatureDate: '2023-08-31',
            consumerBic: 'INGBNL2A', // Wrong BIC
        );

        $mock = self::createMock(CreateDirectDebitMandateAction::class);
        $mock->expects(self::exactly(2))
            ->method('execute')
            ->with(
                ...self::withConsecutive(
                    [
                        $this->customer,
                        $mollieMandateCreateDTO->consumerName,
                        $mollieMandateCreateDTO->consumerAccount,
                        new CarbonImmutable($mollieMandateCreateDTO->signatureDate),
                        $mollieMandateCreateDTO->consumerBic,
                    ],
                    [
                        $this->customer,
                        $mollieMandateCreateDTO->consumerName,
                        $mollieMandateCreateDTO->consumerAccount,
                        new CarbonImmutable($mollieMandateCreateDTO->signatureDate),
                        null, // Without BIC!
                    ]
                )
            )
            ->willReturnCallback(
                function (
                    Customer $customer,
                    string $consumerName,
                    string $consumerAccount, // IBAN
                    CarbonImmutable $signatureDate, // Y-m-d will be used
                    string|null $consumerBic = null
                ): Mandate {
                    if ($consumerBic !== null) {
                        throw new MollieMandateApiException(
                            status: 422,
                            title: 'Unprocessable Entity',
                            detail: 'The BIC is invalid',
                            field: 'consumerBic'
                        );
                    }

                    // Return a valid mandate if no BIC
                    $mollieCustomer = new MollieCustomer();
                    $mollieCustomer->mollie_customer_reference_id = 'cst_1';

                    $this->customer->mollieCustomer()->save($mollieCustomer);

                    $mandate = new Mandate();
                    $mandate->mollie_mandate_reference_id = 'mdt_1';
                    $mandate->payt_mandate_reference_id = '5';
                    $mandate->signature_date = CarbonImmutable::createFromDate(2023, 8, 31)->toImmutable();
                    $mandate->method = MollieMandateMethod::DIRECTDEBIT;

                    $mollieCustomer->mandates()->save($mandate);

                    return $mandate->refresh();
                }
            );

        $job = new CreateDirectDebitMandateJob(
            customer: $this->customer,
            mollieMandateDirectDebitCreateDTO: $mollieMandateCreateDTO,
            migratedCustomer: $this->migratedCustomer
        );

        $job->handle(
            $mock,
            self::resolve(LoggerInterface::class),
            self::resolve(Dispatcher::class),
        );

        $this->migratedCustomer->refresh();

        $coupledMandate = $this->migratedCustomer->mandates()->first();

        self::assertInstanceOf(Mandate::class, $coupledMandate);

        self::assertSame('mdt_1', $coupledMandate->mollie_mandate_reference_id);
        self::assertSame('5', $coupledMandate->payt_mandate_reference_id);
    }
}
