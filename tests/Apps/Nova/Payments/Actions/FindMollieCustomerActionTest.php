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
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Payments\Actions\NovaFindMollieCustomerAction;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerResponseDTO;
use Waterfront\Infra\MollieClient\MollieCustomerClient;

#[CoversClass(NovaFindMollieCustomerAction::class)]
class FindMollieCustomerActionTest extends IntegrationTestCase
{
    private CarbonImmutable $baseTime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseTime = CarbonImmutable::createFromTimeString('2023-08-11T12:34:03.000+02:00');
    }

    #[Test]
    public function fetchMandateAction(): void
    {
        $this->app->bind(function (): MollieCustomerClient {
            $mock = self::createStub(MollieCustomerClient::class);

            $mock->method('getCustomerById')
                ->willReturn($this->getMollieCustomerResponseDTO());

            return $mock;
        });

        $action   = self::resolve(NovaFindMollieCustomerAction::class);
        $fields   = $this->getActionFields();
        $response = $action->handle($fields);

        self::assertInstanceOf(ActionResponse::class, $response);
        $modal = $response['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        $data = (array) json_decode($modal->payload['code'], true);
        self::assertIsString($data['email']);
        self::assertStringContainsString('test@test.test', $data['email']);
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
