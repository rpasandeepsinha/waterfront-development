<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Payments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Payments\Actions\NovaListMandateAction;
use Waterfront\Domain\Payments\Managers\MollieMandateManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDetailsDTO;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateResponseDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;
use Waterfront\Infra\PaytClient\DTO\PaytMandateResponseDTO;
use Waterfront\Infra\PaytClient\PaytMandateClient;

#[CoversClass(NovaListMandateAction::class)]
class ListMandateActionTest extends IntegrationTestCase
{
    /** @var Collection<int, MollieCustomer> */
    private Collection $mollieCustomers;

    private CarbonImmutable $baseTime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseTime = CarbonImmutable::createFromTimeString('2023-08-11T12:34:03.000+02:00');

        $customer = new CustomerFactory()->createOne();
        $mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne();

        $this->mollieCustomers = new Collection([$mollieCustomer]);
    }

    #[Test]
    public function fetchMandateAction(): void
    {
        $this->app->bind(function (): MollieMandateManager {
            $mock = self::createStub(MollieMandateManager::class);

            $mock->method('listMandates')
                ->willReturn([$this->getMollieMandateResponseDTO()]);

            return $mock;
        });

        $this->app->bind(function (): PaytMandateClient {
            $mock = self::createStub(PaytMandateClient::class);

            $mock->method('getPspMandatesByDebtorNumber')
                ->willReturn([$this->getPaytMandateResponseDTO()]);

            return $mock;
        });

        $action   = self::resolve(NovaListMandateAction::class);
        $fields   = $this->getActionFields();
        $response = $action->handle($fields, $this->mollieCustomers);

        self::assertInstanceOf(ActionResponse::class, $response);
        $modal = $response['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        $data = (array) json_decode($modal->payload['code'], true);
        self::assertIsArray($data['Mollie']);
        self::assertIsArray($data['Payt']);
        self::assertSame('mnd-tttt', $data['Mollie'][0]['id']);
        self::assertSame('5', $data['Payt'][0]['id']);
    }

    private function getActionFields(): ActionFields
    {
        return new ActionFields(
            new Collection([]),
            new Collection([])
        );
    }

    private function getMollieMandateResponseDTO(): MollieMandateResponseDTO
    {
        return new MollieMandateResponseDTO(
            resource: 'test',
            id: 'mnd-tttt',
            mode: 'test',
            status: MollieMandateStatus::VALID,
            method: MollieMandateMethod::DIRECTDEBIT,
            details: new MollieMandateDetailsDTO(
                consumerName: 'name',
                consumerAccount: 'account',
                consumerBic: 'bic'
            ),
            mandateReference: 'mdt_test_1',
            signatureDate: $this->baseTime->toDateString(),
            createdAt: $this->baseTime
        );
    }

    private function getPaytMandateResponseDTO(): PaytMandateResponseDTO
    {
        return new PaytMandateResponseDTO(
            id: '5',
            mandateIdentifier: 'mdt_test_1',
        );
    }
}
