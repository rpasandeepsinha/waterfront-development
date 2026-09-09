<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\MigrateProvisionResultData;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\MigrateProvisionResultData\NovaMigrateProvisionResultDataAction;
use Waterfront\Apps\OneOffScripts\MigrateProvisionResultData\ProvisionResultUpdateJob;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;

#[CoversClass(NovaMigrateProvisionResultDataAction::class)]
class NovaMigrateProvisionResultDataActionTest extends IntegrationTestCase
{
    private LoggerInterface $logger;

    private Dispatcher $busDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createStub(LoggerInterface::class);
        $this->busDispatcher = self::createStub(Dispatcher::class);
    }

    #[Test]
    public function handle(): void
    {
        $request = ProvisioningRequestFactory::new()
            ->hosting()
            ->has(
                ProvisioningResultFactory::new()
                    ->success()
                    ->state(['response' => json_encode(['validation_results' => null, 'provisionStatus' => ProvisionStatus::SUCCESS->value])]),
                'result'
            )
            ->createOne();

        $result = $request->result;

        self::assertNotNull($result);

        $this->busDispatcher = self::createMock(Dispatcher::class);
        $this->busDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (mixed $job) use ($result): bool {
                self::assertInstanceOf(ProvisionResultUpdateJob::class, $job);
                self::assertSame($result->id, $job->row->id);
                self::assertFalse($job->dryRun);

                return true;
            }));

        $action = new NovaMigrateProvisionResultDataAction(
            logger: $this->logger,
            busDispatcher: $this->busDispatcher,
        );

        $actionFields = new ActionFields(
            new Collection(['dry-run' => false]),
            new Collection(),
        );

        $actionResponse = $action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame('One-off script started successfully.', (string) $responseData['message']);
    }
}
