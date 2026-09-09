<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Managers;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MandateFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Managers\MandateReferenceGenerator;
use Waterfront\Domain\Payments\Managers\MollieMandateManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandatePayPalCreateDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;
use Waterfront\Infra\MollieClient\MollieMandateClient;

#[CoversClass(MollieMandateManager::class)]
#[AllowMockObjectsWithoutExpectations]
class MollieMandateManagerTest extends IntegrationTestCase
{
    private string $mollieCustomerId = 'cst_kEn1PlbGa';

    private string $mollieMandateId = 'mdt_cvvkyYTg2Y';

    private MollieCustomer $mollieCustomer;

    private MollieMandateManager $mollieMandateManager;

    private MandateReferenceGenerator&MockObject $mandateReferenceGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->mandateReferenceGenerator = self::createMock(MandateReferenceGenerator::class);

        $this->mollieMandateManager = new MollieMandateManager(
            self::resolve(MollieMandateClient::class),
            self::resolve(LoggerInterface::class),
            $this->mandateReferenceGenerator
        );

        $customer = new CustomerFactory()->createOne();

        $this->mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne([
            'mollie_customer_reference_id' => $this->mollieCustomerId,
        ]);
    }

    #[Test]
    public function createDirectDebitMandateExistingAtMollie(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/list_mandates_existing_response.php');
                },
        ]);

        $externalMollieMandate = $this->mollieMandateManager->findOrCreateMandate(
            $this->mollieCustomer,
            new MollieMandateDirectDebitCreateDTO(
                consumerName: 'John',
                consumerAccount: 'NL18RABO0123459876',
                signatureDate: '2012-09-05',
                consumerBic: 'RABONL2U',
            )
        );

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(1, $mandates);

        $mandate = $mandates->firstOrFail();

        self::assertSame('mdt_cvvkyYTg2Y', $mandate->mollie_mandate_reference_id);
        self::assertSame($externalMollieMandate->id, $mandate->mollie_mandate_reference_id);
        self::assertSame('2023-08-02', $externalMollieMandate->signatureDate);
    }

    #[Test]
    public function createPaypalMandateExistingAtMollie(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/list_mandates_existing_response.php');
                },
        ]);

        $externalMollieMandate = $this->mollieMandateManager->findOrCreateMandate(
            $this->mollieCustomer,
            new MollieMandatePayPalCreateDTO(
                consumerName: 'Tester de Test',
                consumerEmail: 'test@test.com',
                paypalBillingAgreementId: 'asdfasdf',
                signatureDate: '2023-09-05',
            )
        );

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(1, $mandates);

        $mandate = $mandates->firstOrFail();

        self::assertSame('mdt_Uq9stfyFwz', $mandate->mollie_mandate_reference_id);
        self::assertSame($externalMollieMandate->id, $mandate->mollie_mandate_reference_id);
        self::assertSame('2023-08-01', $externalMollieMandate->signatureDate);
    }

    #[Test]
    public function createDirectDebitMandateNew(): void
    {
        $this->mandateReferenceGenerator
            ->expects(self::once())
            ->method('generateMandateReference')
            ->willReturn('C1M1');

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    if ($request->method() === 'GET') {
                        return Http::response(include __DIR__ . '/data/mandates/list_mandates_no_mandates_response.php');
                    }

                    if ($request->method() === 'POST') {
                        self::assertSame(
                            [
                                'method' => 'directdebit',
                                'consumerName' => 'John',
                                'consumerAccount' => 'NL18RABO0123459876',
                                'signatureDate' => '2023-09-05',
                                'consumerBic' => 'RABONL2U',
                                'mandateReference' => 'C1M1',
                            ],
                            $request->data()
                        );

                        return Http::response(include __DIR__ . '/data/mandates/create_mandate_directdebit_response.php');
                    }

                    self::fail('Unknown request');
                },
        ]);

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(0, $mandates);

        $externalMollieMandate = $this->mollieMandateManager->findOrCreateMandate(
            $this->mollieCustomer,
            new MollieMandateDirectDebitCreateDTO(
                consumerName: 'John',
                consumerAccount: 'NL18RABO0123459876',
                signatureDate: '2023-09-05',
                consumerBic: 'RABONL2U',
            )
        );

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(1, $mandates);

        $mandate = $mandates->firstOrFail();

        self::assertSame('mdt_h3gAaD5zP', $mandate->mollie_mandate_reference_id);
        self::assertSame($externalMollieMandate->id, $mandate->mollie_mandate_reference_id);
        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $mandate->method);
        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $externalMollieMandate->method);
    }

    #[Test]
    public function createPaypalMandateNew(): void
    {
        $this->mandateReferenceGenerator
            ->expects(self::once())
            ->method('generateMandateReference')
            ->willReturn('C1M1');

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    if ($request->method() === 'GET') {
                        return Http::response(include __DIR__ . '/data/mandates/list_mandates_no_mandates_response.php');
                    }

                    if ($request->method() === 'POST') {
                        self::assertSame(
                            [
                                'method' => 'paypal',
                                'consumerName' => 'John',
                                'consumerEmail' => 'tessdfgs',
                                'paypalBillingAgreementId' => 'asdfasdf',
                                'signatureDate' => '2023-09-05',
                                'mandateReference' => 'C1M1',
                            ],
                            $request->data()
                        );

                        return Http::response(include __DIR__ . '/data/mandates/create_mandate_paypal_response.php');
                    }

                    self::fail('Unknown request');
                },
        ]);

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(0, $mandates);

        $externalMollieMandate = $this->mollieMandateManager->findOrCreateMandate(
            $this->mollieCustomer,
            new MollieMandatePayPalCreateDTO(
                consumerName: 'John',
                consumerEmail: 'tessdfgs',
                paypalBillingAgreementId: 'asdfasdf',
                signatureDate: '2023-09-05',
            )
        );

        $mandates = $this->mollieCustomer->mandates()->get();

        self::assertCount(1, $mandates);

        $mandate = $mandates->firstOrFail();

        self::assertSame('mdt_Uq9stfyFwz', $mandate->mollie_mandate_reference_id);
        self::assertSame($externalMollieMandate->id, $mandate->mollie_mandate_reference_id);
        self::assertSame(MollieMandateMethod::PAYPAL, $mandate->method);
        self::assertSame(MollieMandateMethod::PAYPAL, $externalMollieMandate->method);
    }

    #[Test]
    public function getMandate(): void
    {
        $mollieMandateModel = new MandateFactory()->for($this->mollieCustomer)->createOne([
            'mollie_mandate_reference_id' => $this->mollieMandateId,
        ]);

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    $expectedListData = include __DIR__ . '/data/mandates/get_mandate_response.php';

                    return Http::response($expectedListData);
                },
        ]);

        $mollieMandate = $this->mollieMandateManager->getMandate(
            $this->mollieCustomer,
            $mollieMandateModel
        );

        self::assertSame('mdt_Uq9stfyFwz', $mollieMandate->id);
        self::assertSame(MollieMandateStatus::VALID, $mollieMandate->status);
        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $mollieMandate->method);
        self::assertSame('Tester de Test', $mollieMandate->details->consumerName);
        self::assertSame('NL18RABO0123459876', $mollieMandate->details->consumerAccount);
        self::assertNull($mollieMandate->details->consumerBic);
        self::assertNull($mollieMandate->mandateReference);
        self::assertSame('2023-08-02', $mollieMandate->signatureDate);
        self::assertSame('2023-08-01 12:44:28', $mollieMandate->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function listMandates(): void
    {
        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates" =>
                function (Request $request) {
                    self::assertSame('GET', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/list_mandates_response.php');
                },
        ]);

        $mollieMandates = $this->mollieMandateManager->listMandates($this->mollieCustomer);

        self::assertCount(2, $mollieMandates);

        $directdebitMandate = $mollieMandates[0];

        self::assertSame(MollieMandateMethod::DIRECTDEBIT, $directdebitMandate->method);
        self::assertSame('NL18RABO0123459876', $directdebitMandate->details->consumerAccount);

        $paypalMandate = $mollieMandates[1];

        self::assertSame(MollieMandateMethod::PAYPAL, $paypalMandate->method);
        self::assertSame('test@test.com', $paypalMandate->details->consumerAccount);
    }
}
