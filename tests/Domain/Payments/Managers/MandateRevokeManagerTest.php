<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Managers;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MandateFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Exceptions\PaytMandateStillExistsException;
use Waterfront\Domain\Payments\Managers\MandateRevokeManager;
use Waterfront\Domain\Payments\Models\Mandate;

#[CoversClass(MandateRevokeManager::class)]
class MandateRevokeManagerTest extends IntegrationTestCase
{
    private string $mollieCustomerId = 'cst_gbPhDjoPSn';

    private string $mollieMandateId = 'mdt_Uq9stfyFwz';

    private string $paytMandateId = '5678';

    private MandateRevokeManager $manager;

    private Mandate $mandate;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $customer = new CustomerFactory()->createOne();
        $customer->customer_number = 10001234; // doesn't work when you use the factory
        $customer->save();

        $mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne([
            'mollie_customer_reference_id' => $this->mollieCustomerId,
        ]);

        $this->mandate = new MandateFactory()->for($mollieCustomer)->createOne([
            'mollie_mandate_reference_id' => $this->mollieMandateId,
            'payt_mandate_reference_id' => $this->paytMandateId,
        ]);

        $this->manager = self::resolve(MandateRevokeManager::class);
    }

    #[Test]
    public function revokeSuccessfulDoesntExistAtMollie(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5678' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(['data' => []]);
            },

            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('DELETE', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/errors/mollie_gone.php');
                },
        ]);

        $this->manager->revokeMandate($this->mandate);

        $this->mandate->refresh();

        self::assertFalse($this->mandate->exists());
    }

    #[Test]
    public function revokeSuccessfulExistsAtMollie(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5678' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(['data' => []]);
            },

            "api.mollie.sandwaveio.test/v2/customers/$this->mollieCustomerId/mandates/$this->mollieMandateId" =>
                function (Request $request) {
                    self::assertSame('DELETE', $request->method());

                    return Http::response(include __DIR__ . '/data/mandates/get_mandate_response.php');
                },
        ]);

        $this->manager->revokeMandate($this->mandate);

        $this->mandate->refresh();

        self::assertFalse($this->mandate->exists());
    }

    #[Test]
    public function revokeThrowsPaytMandateStillExistsException(): void
    {
        Http::fake([
            'api.paytsoftware.test/v1/psp_mandates?administration_id=1234&ids=5678' => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/payt_mandates/get_psp_mandates_response.php');
            },
        ]);

        self::expectException(PaytMandateStillExistsException::class);
        self::expectExceptionMessageIsOrContains(
            'Payt mandate ID 5678 still exists for mollie mandate mdt_Uq9stfyFwz and mollie customer cst_gbPhDjoPSn',
        );

        $this->manager->revokeMandate($this->mandate);
    }
}
