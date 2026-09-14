<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Managers;

use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MandateFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Exceptions\PaytMandateReferenceNullException;
use Waterfront\Domain\Payments\Managers\PaytMandateManager;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDetailsDTO;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateResponseDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;

#[CoversClass(PaytMandateManager::class)]
class PaytMandateManagerTest extends IntegrationTestCase
{
    private string $mollieCustomerId = 'cst_gbPhDjoPSn';

    private string $mollieMandateId = 'mdt_Uq9stfyFwz';

    private PaytMandateManager $paytMandateManager;

    private Mandate $mandate;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->paytMandateManager = self::resolve(PaytMandateManager::class);

        $customer = new CustomerFactory()->createOne();
        $customer->customer_number = 10001234; // doesn't work when you use the factory
        $customer->save();

        $mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne([
            'mollie_customer_reference_id' => $this->mollieCustomerId,
        ]);

        $this->mandate = new MandateFactory()->for($mollieCustomer)->createOne([
            'mollie_mandate_reference_id' => $this->mollieMandateId,
        ]);
    }

    #[Test]
    public function createMandateDoesntExistYetSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=10001234' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(['data' => []]);
                },

            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/get_mandate_response.php');
                },

            'api.paytsoftware.test/v1/psp_mandates' => function (Request $request) {
                self::assertSame('POST', $request->method());

                self::assertSame(
                    [
                        'administration_id' => '1234',
                        'psp_mandates' => [
                            [
                                'bank_account_name' => 'Tester de Test',
                                'bank_account_number' => 'NL18RABO0123459876',
                                'mandate_identifier' => 'mdt_Uq9stfyFwz',
                                'debtor_code' => '10001234',
                                'customer_identifier' => 'cst_gbPhDjoPSn',
                                'provider_code' => 'mollie',
                            ],
                        ],
                        'fields' => [
                            'only' => [
                                'id',
                                'mandate_identifier',
                            ],
                        ],
                    ],
                    $request->data(),
                );

                return Http::response(include __DIR__ . '/data/payt_mandates/create_mandate_response.php', 201);
            },
        ]);

        self::assertNull($this->mandate->payt_mandate_reference_id);

        $mandateResponse = $this->paytMandateManager->findOrCreateMandate($this->mandate, $this->getMollieResponse());

        $this->mandate->refresh();

        self::assertSame('5678', $this->mandate->payt_mandate_reference_id);
        self::assertSame('5678', $mandateResponse->id);
        self::assertSame('mdt_Uq9stfyFwz', $mandateResponse->mandateIdentifier);
    }

    /**
     * @see https://sandwaveio.slack.com/archives/C0230TSUW3Y/p1727863853235139
     */
    #[Test]
    public function createMandateIdIsNull(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=10001234' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(['data' => []]);
                },

            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/get_mandate_response.php');
                },

            'api.paytsoftware.test/v1/psp_mandates' => function (Request $request) {
                self::assertSame('POST', $request->method());

                self::assertSame(
                    [
                        'administration_id' => '1234',
                        'psp_mandates' => [
                            [
                                'bank_account_name' => 'Tester de Test',
                                'bank_account_number' => 'NL18RABO0123459876',
                                'mandate_identifier' => 'mdt_Uq9stfyFwz',
                                'debtor_code' => '10001234',
                                'customer_identifier' => 'cst_gbPhDjoPSn',
                                'provider_code' => 'mollie',
                            ],
                        ],
                        'fields' => [
                            'only' => [
                                'id',
                                'mandate_identifier',
                            ],
                        ],
                    ],
                    $request->data(),
                );

                return Http::response(include __DIR__ . '/data/payt_mandates/create_mandate_response_null.php', 201);
            },
        ]);

        self::assertNull($this->mandate->payt_mandate_reference_id);

        $mandateResponse = $this->paytMandateManager->findOrCreateMandate($this->mandate, $this->getMollieResponse());

        $this->mandate->refresh();

        self::assertNull($this->mandate->payt_mandate_reference_id);
        self::assertNull($mandateResponse->id);
        self::assertNull($mandateResponse->mandateIdentifier);
    }

    #[Test]
    public function createMandateAlreadyExistsSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=10001234' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/payt_mandates/get_psp_mandates_response.php');
                },
        ]);

        self::assertNull($this->mandate->payt_mandate_reference_id);

        $mandateResponse = $this->paytMandateManager->findOrCreateMandate($this->mandate, $this->getMollieResponse());

        $this->mandate->refresh();

        self::assertSame('5678', $this->mandate->payt_mandate_reference_id);
        self::assertSame('5678', $mandateResponse->id);
        self::assertSame('mdt_Uq9stfyFwz', $mandateResponse->mandateIdentifier);
    }

    #[Test]
    public function createMandateWithInvalidValuesShouldThrowException(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=10001234' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(['data' => []]);
                },
        ]);

        $mollieMandate = new MollieMandateResponseDTO(
            resource: 'x',
            id: 'x',
            mode: 'x',
            status: MollieMandateStatus::PENDING,
            method: MollieMandateMethod::DIRECTDEBIT,
            details: new MollieMandateDetailsDTO(null, null, 'x'),
            mandateReference: 'x',
            signatureDate: 'x',
            createdAt: new DateTimeImmutable(),
        );

        $this->expectException(InvalidArgumentException::class);

        $this->paytMandateManager->findOrCreateMandate($this->mandate, $mollieMandate);
    }

    #[Test]
    public function getMandatesSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5678' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/payt_mandates/get_psp_mandates_response.php');
            },
        ]);

        $this->mandate->payt_mandate_reference_id = '5678';
        $this->mandate->save();

        $paytMandate = $this->paytMandateManager->getMandate($this->mandate);

        self::assertNotNull($paytMandate);
        self::assertSame('5678', $paytMandate->id);
        self::assertSame('mdt_Uq9stfyFwz', $paytMandate->mandateIdentifier);
    }

    #[Test]
    public function paytMandateReferenceNullException(): void
    {
        self::expectException(PaytMandateReferenceNullException::class);
        self::expectExceptionMessageIs(sprintf('Payt mandate reference null for Mandate "%s"', $this->mandate->id));

        $this->paytMandateManager->getMandate($this->mandate);
    }

    private function getMollieResponse(): MollieMandateResponseDTO
    {
        return new MollieMandateResponseDTO(
            resource: 'x',
            id: 'x',
            mode: 'x',
            status: MollieMandateStatus::PENDING,
            method: MollieMandateMethod::DIRECTDEBIT,
            details: new MollieMandateDetailsDTO('Tester de Test', 'NL18RABO0123459876', 'x'),
            mandateReference: 'x',
            signatureDate: 'x',
            createdAt: new DateTimeImmutable(),
        );
    }
}
