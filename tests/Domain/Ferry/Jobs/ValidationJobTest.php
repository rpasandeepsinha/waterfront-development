<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Jobs\ValidationJob;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(ValidationJob::class)]
class ValidationJobTest extends IntegrationTestCase
{
    #[Test]
    public function handleThrowsUnexpectedException(): void
    {
        Http::fake();

        $exceptionMessage  = 'Something went completely wrong. Sorry :(';
        $customerReference = 'reference';
        $configuration = self::resolve(ConfigurationInterface::class);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');
        $apiUrl = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url');

        $webhookPayload = [
            'type' => AzureDataFactoryMessageType::VALIDATION_EXECUTED->value,
            'data' => [
                'reference' => $customerReference,
                'results' => [
                    'message'   => 'Uncaught exception occurred in the validation pipelines. Please contact Ferry development',
                    'exception' => $exceptionMessage,
                ],
                'timeline'  => [],
            ],
        ];

        $expectedUrl = sprintf('%s/api/ConsumeFerryResponse', $apiUrl);

        Http::shouldReceive('post')->withArgs(function ($url, $payload) use ($expectedUrl, $webhookPayload) {
            self::assertSame($expectedUrl, $url);
            self::assertSame($webhookPayload, $payload);
        });

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();

        Http::shouldReceive('post')->with($expectedUrl, $webhookPayload)->andReturn(new LaravelResponse(new Response()));

        $customer = include(__DIR__ . '/../Pipes/data/customer_bad_data.php');
        $subscriptions = include(__DIR__ . '/../Pipes/data/subscriptions_correct.php');

        $validationPayload = new ValidationPayload(
            validationReference: $customerReference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $pipes = self::createStub(Pipeline::class);
        $pipes->method('send')->willThrowException(new Exception($exceptionMessage));

        $job = new ValidationJob($validationPayload, []);

        self::expectException(Exception::class);
        self::expectExceptionMessageIs($exceptionMessage);

        $job->handle($pipes, $dispatcher, $logger);
    }
}
