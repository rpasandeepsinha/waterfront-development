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
use Waterfront\Apps\Nova\Payments\Actions\NovaFetchMollieCustomerAction;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerResponseDTO;

#[CoversClass(NovaFetchMollieCustomerAction::class)]
class FetchMollieCustomerActionTest extends IntegrationTestCase
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
        $this->app->bind(function (): MollieCustomerManager {
            $mock = self::createStub(MollieCustomerManager::class);

            $mock->method('findByMollieCustomer')
                ->willReturn($this->getMollieCustomerResponseDTO());

            return $mock;
        });

        $action   = self::resolve(NovaFetchMollieCustomerAction::class);
        $fields   = $this->getActionFields();
        $response = $action->handle($fields, $this->mollieCustomers);

        self::assertInstanceOf(ActionResponse::class, $response);
        $modal = $response['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        $data = (array) json_decode($modal->payload['code'], true);
        self::assertSame('cst_test_1', $data['id']);
    }

    private function getActionFields(): ActionFields
    {
        return new ActionFields(
            new Collection([
                'mollie_customer_reference_id' => 'cst_test_1',
            ]),
            new Collection([])
        );
    }

    private function getMollieCustomerResponseDTO(): MollieCustomerResponseDTO
    {
        return new MollieCustomerResponseDTO(
            id: 'cst_test_1',
            mode: 'test',
            name: 'Tester Test',
            email: 'test@test.test',
            locale: 'nl_NL',
            metadata: new MollieCustomerMetadataDTO(
                debtorId: 100123,
            ),
            createdAt: $this->baseTime
        );
    }
}
