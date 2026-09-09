<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\MigrateProvisionResultData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\MigrateProvisionResultData\ProvisionResultUpdateJob;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;

#[CoversClass(ProvisionResultUpdateJob::class)]
class ProvisionResultUpdateJobTest extends IntegrationTestCase
{
    #[Test]
    public function handleWillMigrateProvisioningResultResponse(): void
    {
        $request = ProvisioningRequestFactory::new()
            ->hosting()
            ->has(
                ProvisioningResultFactory::new()
                    ->success()
                    ->state(['response' => json_encode(['validation_results' => null, 'status' => 'success'])]),
                'result'
            )
            ->createOne();

        $result = $request->result;

        self::assertNotNull($result);

        $job = new ProvisionResultUpdateJob(
            row: (object) [
                'id' => $result->id,
                'response' => $result->response,
                'status' => $result->status->value,
            ],
            dryRun: false,
        );

        $job->handle(self::createStub(LoggerInterface::class));

        self::assertDatabaseHas('provisioning_results', [
            'id' => $result->id,
            'response' => json_encode([
                'provisionStatus' => ProvisionStatus::SUCCESS->value,
                'validationResult' => [],
            ]),
        ]);

        self::assertDatabaseMissing('provisioning_results', [
            'id' => $result->id,
            'response' => json_encode([
                'validation_results' => null,
                'status' => 'success',
            ]),
        ]);
    }

    #[Test]
    public function handleWillNotUpdateProvisioningResultResponseDuringDryRun(): void
    {
        $request = ProvisioningRequestFactory::new()
            ->hosting()
            ->has(
                ProvisioningResultFactory::new()
                    ->success()
                    ->state(['response' => json_encode(['validation_results' => null, 'status' => 'success'])]),
                'result'
            )
            ->createOne();

        $result = $request->result;

        self::assertNotNull($result);

        $job = new ProvisionResultUpdateJob(
            row: (object) [
                'id' => $result->id,
                'response' => $result->response,
                'status' => $result->status->value,
            ],
            dryRun: true,
        );

        $job->handle(self::createStub(LoggerInterface::class));

        self::assertDatabaseHas('provisioning_results', [
            'id' => $result->id,
            'response' => json_encode([
                'validation_results' => null,
                'status' => 'success',
            ]),
        ]);

        self::assertDatabaseMissing('provisioning_results', [
            'id' => $result->id,
            'response' => json_encode([
                'provisionStatus' => ProvisionStatus::SUCCESS->value,
                'validationResult' => [],
            ]),
        ]);
    }
}
