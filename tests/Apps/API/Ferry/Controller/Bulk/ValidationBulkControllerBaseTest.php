<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller\Bulk;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as SymphonyResponse;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\ValidationBulkController;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;

#[CoversClass(ValidationBulkController::class)]
class ValidationBulkControllerBaseTest extends IntegrationTestCase
{
    #[Test]
    public function bulkValidateCompleteSuccess(): void
    {
        Http::fake();
        Http::shouldReceive('post')
            ->twice()
            ->andReturn(new LaravelResponse(new Response()));

        Http::shouldReceive('withHeaders')->twice()->andReturnSelf();

        $pipelineMock = self::createMock(Pipeline::class);

        $pipelineMock->expects(self::exactly(2))
            ->method('through')
            ->willReturn($pipelineMock);

        $pipelineMock->expects(self::exactly(2))
            ->method('send')
            ->willReturn($pipelineMock);

        $pipelineMock->expects(self::exactly(2))
            ->method('via')
            ->willReturn($pipelineMock);

        $fakePayload = new ValidationPayload('fake', [], []);

        $pipelineMock->expects(self::exactly(2))
            ->method('then')
            ->willReturn($fakePayload);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $json = (string) file_get_contents(__DIR__ . '/data/validation/bulk_validation_fake.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate.bulk'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_MULTI_STATUS);
    }
}
