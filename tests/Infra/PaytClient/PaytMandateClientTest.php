<?php

declare(strict_types=1);

namespace Tests\Infra\PaytClient;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\PaytClient\DTO\PaytMandateCreateDTO;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateIdNotNumericException;
use Waterfront\Infra\PaytClient\PaytMandateClient;

#[CoversClass(PaytMandateClient::class)]
class PaytMandateClientTest extends IntegrationTestCase
{
    private PaytMandateClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->client = self::resolve(PaytMandateClient::class);
    }

    #[Test]
    public function createMandateSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates' => function (Request $request) {
                self::assertSame('POST', $request->method());

                self::assertSame(
                    [
                        'administration_id' => '1234',
                        'psp_mandates' => [
                            [
                                'bank_account_name' => 'Tester de Test',
                                'bank_account_number' => 'NL18RABO0123459876',
                                'mandate_identifier' => 'mdt_test1234',
                                'debtor_code' => '10001234',
                                'customer_identifier' => 'cst_test1234',
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

                return Http::response(include __DIR__ . '/data/psp_mandates/create_psp_mandates_response.php', 201);
            },
        ]);

        $paytMandate = new PaytMandateCreateDTO(
            bankAccountName: 'Tester de Test',
            bankAccountNumber: 'NL18RABO0123459876',
            mandateIdentifier: 'mdt_test1234',
            debtorCode: '10001234', // customer number
            customerIdentifier: 'cst_test1234',
        );

        $pspMandate = $this->client->createPspMandate($paytMandate);

        self::assertSame('5678', $pspMandate->id);
        self::assertSame('mdt_test1234', $pspMandate->mandateIdentifier);
    }

    #[Test]
    public function getPspMandatesByDebtorNumberSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&debtor_numbers=1000005' =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/psp_mandates/get_psp_mandates_response.php');
                },
        ]);

        $pspMandates = $this->client->getPspMandatesByDebtorNumber('1000005');

        self::assertCount(1, $pspMandates);

        $pspMandate = $pspMandates[0];

        self::assertSame('5', $pspMandate->id);
        self::assertSame('mdt_test1234', $pspMandate->mandateIdentifier);
    }

    #[Test]
    public function getPspMandatesByPaytIdSuccess(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/psp_mandates/get_psp_mandates_response.php');
            },
        ]);

        $pspMandate = $this->client->getPspMandatesByPaytId('5');

        self::assertNotNull($pspMandate);
        self::assertSame('5', $pspMandate->id);
        self::assertSame('mdt_test1234', $pspMandate->mandateIdentifier);
    }

    #[Test]
    public function getPspMandatesThrowsNotNumericException(): void
    {
        self::expectException(PaytMandateIdNotNumericException::class);
        self::expectExceptionMessageIs('Payt mandate ID is not numeric: 7,8');

        $this->client->getPspMandatesByPaytId('7,8');
    }

    #[Test]
    public function getPspMandateByPaytIdNotFoundEmptyResponse(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(['data' => []]);
            },
        ]);

        $pspMandates = $this->client->getPspMandatesByPaytId('5');
        self::assertEmpty($pspMandates);
    }

    #[Test]
    public function createMandateUnprocessableEntity(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates' => function (Request $request) {
                self::assertSame('POST', $request->method());

                return Http::response(include __DIR__ . '/data/errors/unprocessable_entity.php', 422);
            },
        ]);

        $paytMandate = new PaytMandateCreateDTO(
            bankAccountName: 'Tester de Test',
            bankAccountNumber: 'NL18RABO0123459876',
            mandateIdentifier: 'mdt_test1234',
            debtorCode: '10001234', // customer number
            customerIdentifier: 'cst_test1234',
        );

        self::expectException(PaytMandateApiException::class);
        self::expectExceptionMessageIs('psp_mandates: duplicate_debtor_code');
        self::expectExceptionCode(422);

        $this->client->createPspMandate($paytMandate);
    }
}
