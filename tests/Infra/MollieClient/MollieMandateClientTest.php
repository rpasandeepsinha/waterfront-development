<?php

declare(strict_types=1);

namespace Tests\Infra\MollieClient;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandatePayPalCreateDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\MollieMandateClient;

#[CoversClass(MollieMandateClient::class)]
class MollieMandateClientTest extends IntegrationTestCase
{
    private string $mollieCustomerId = 'cst_kEn1PlbGa';

    private string $mollieMandateId = 'mdt_cvvkyYTg2Y';

    private MollieMandateClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->client = self::resolve(MollieMandateClient::class);
    }

    #[Test]
    public function createMandateSuccessDirectDebit(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    self::assertSame(
                        [
                            'method' => 'directdebit',
                            'consumerName' => 'John',
                            'consumerAccount' => 'NL18RABO0123459876',
                            'signatureDate' => '2023-05-08',
                            'consumerBic' => 'RABONL2U',
                            'mandateReference' => 'test',
                        ],
                        $request->data()
                    );

                    $expectedListData = include __DIR__ . '/data/mandates/create_mandate_directdebit_response.php';

                    return Http::response($expectedListData, 201);
                },
        ]);

        $mollieMandate = $this->client->createMandate(
            $this->mollieCustomerId,
            new MollieMandateDirectDebitCreateDTO(
                consumerName: 'John',
                consumerAccount: 'NL18RABO0123459876',
                signatureDate: '2023-05-08',
                consumerBic: 'RABONL2U',
                mandateReference: 'test',
            )
        );

        self::assertSame('mdt_h3gAaD5zP', $mollieMandate->id);
        self::assertSame(MollieMandateStatus::VALID, $mollieMandate->status);
        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $mollieMandate->method);
        self::assertSame('John Doe', $mollieMandate->details->consumerName);
        self::assertSame('NL55INGB0000000000', $mollieMandate->details->consumerAccount);
        self::assertSame('INGBNL2A', $mollieMandate->details->consumerBic);
        self::assertSame('YOUR-COMPANY-MD13804', $mollieMandate->mandateReference);
        self::assertSame('2018-05-08', $mollieMandate->signatureDate);
        self::assertSame('2018-05-07 10:49:08', $mollieMandate->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function createMandateUnprocessableEntityDirectDebit(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/errors/directdebit_unprocessable_entity.php';

                    return Http::response($expectedListData, 422);
                },
        ]);

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionCode(422);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                422,
                'Unprocessable Entity',
                'The bank account is invalid',
                'consumerAccount'
            )
        );

        $this->client->createMandate(
            $this->mollieCustomerId,
            new MollieMandateDirectDebitCreateDTO(
                consumerName: 'John',
                consumerAccount: 'COMPLETELY_WRONG',
                signatureDate: '2023-09-05',
                consumerBic: 'NOTGOOD',
            )
        );
    }

    #[Test]
    public function createMandateSuccessPaypal(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/create_mandate_paypal_response.php';

                    return Http::response($expectedListData, 201);
                },
        ]);

        $mollieMandate = $this->client->createMandate(
            $this->mollieCustomerId,
            new MollieMandatePayPalCreateDTO(
                consumerName: 'Tester de Test',
                consumerEmail: 'test@testing.test',
                paypalBillingAgreementId: 'paypalTestBillingAgreement',
                signatureDate: '2023-09-05',
            )
        );

        self::assertSame('mdt_Uq9stfyFwz', $mollieMandate->id);
        self::assertSame(MollieMandateStatus::VALID, $mollieMandate->status);
        self::assertSame(MollieMandateMethod::PAYPAL, $mollieMandate->method);
        self::assertSame('Tester de Test', $mollieMandate->details->consumerName);
        self::assertSame('test@testing.test', $mollieMandate->details->consumerAccount);
        self::assertNull($mollieMandate->details->consumerBic);
        self::assertNull($mollieMandate->mandateReference);
        self::assertSame('2023-08-02', $mollieMandate->signatureDate);
        self::assertSame('2023-08-01 12:44:28', $mollieMandate->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function createMandateUnprocessableEntityPaypal(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('POST', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/errors/paypal_unprocessable_entity.php';

                    return Http::response($expectedListData, 422);
                },
        ]);

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionCode(422);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                422,
                'Unprocessable Entity',
                'The Billing Agreement ID does already exist.',
                'paypalBillingAgreementId'
            )
        );

        $this->client->createMandate(
            $this->mollieCustomerId,
            new MollieMandatePayPalCreateDTO(
                consumerName: 'John',
                consumerEmail: 'test@testing.test',
                paypalBillingAgreementId: 'testing',
                signatureDate: '2023-09-05',
            )
        );
    }

    #[Test]
    public function getMandate(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/get_mandate_paypal_response.php';

                    return Http::response($expectedListData);
                },
        ]);

        $mollieMandate = $this->client->getMandate(
            $this->mollieCustomerId,
            $this->mollieMandateId
        );

        self::assertSame('mdt_Uq9stfyFwz', $mollieMandate->id);
        self::assertSame(MollieMandateStatus::VALID, $mollieMandate->status);
        self::assertSame(MollieMandateMethod::PAYPAL, $mollieMandate->method);
        self::assertSame('Tester de Test', $mollieMandate->details->consumerName);
        self::assertSame('test@test.com', $mollieMandate->details->consumerAccount);
        self::assertNull($mollieMandate->details->consumerBic);
        self::assertNull($mollieMandate->mandateReference);
        self::assertSame('2023-08-02', $mollieMandate->signatureDate);
        self::assertSame('2023-08-01 12:44:28', $mollieMandate->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function getMandateNotFound(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/errors/not_found.php';

                    return Http::response($expectedListData, 404);
                },
        ]);

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'No mandate exists with token mdt_Uq9stfyFwa.',
                ''
            )
        );

        $this->client->getMandate(
            $this->mollieCustomerId,
            $this->mollieMandateId
        );
    }

    #[Test]
    public function listMandate(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/list_mandates_response.php');
                },
        ]);

        $mandates = $this->client->listMandates($this->mollieCustomerId);

        self::assertCount(2, $mandates);

        $directdebitMandate = $mandates[0];

        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $directdebitMandate->method);
        self::assertSame('NL18RABO0123459876', $directdebitMandate->details->consumerAccount);

        $paypalMandate = $mandates[1];

        self::assertSame(MollieMandateMethod::PAYPAL, $paypalMandate->method);
        self::assertSame('test@test.com', $paypalMandate->details->consumerAccount);
    }

    #[Test]
    public function listMandateNotFound(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/errors/list_not_found.php', 404);
                },
        ]);

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'No customer exists with token cst_gbPhDjoPSn.',
                ''
            )
        );

        $this->client->listMandates($this->mollieCustomerId);
    }

    #[Test]
    public function revokeMandate(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('DELETE', $request->method());

                    return Http::response(null, 204);
                },
        ]);

        $this->client->revokeMandate($this->mollieCustomerId, $this->mollieMandateId);
    }

    #[Test]
    public function revokeMandateAlreadyGone(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('DELETE', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/errors/gone.php', 410);
                },
        ]);

        self::expectException(MollieMandateApiException::class);
        self::expectExceptionCode(410);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                410,
                'Gone',
                'The mandate is no longer available',
                ''
            )
        );

        $this->client->revokeMandate($this->mollieCustomerId, $this->mollieMandateId);
    }
}
