<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Legacy;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Legacy\NovaBulkUpdateDomainHandlePrivacyProtectAction;
use Waterfront\Apps\OneOffScripts\Legacy\NovaUpdateDomainHandlePrivacyProtectJob;

#[CoversClass(NovaBulkUpdateDomainHandlePrivacyProtectAction::class)]
class NovaBulkUpdateDomainHandlePrivacyProtectActionTest extends IntegrationTestCase
{
    private MockObject&Dispatcher $dispatcher;

    private NovaBulkUpdateDomainHandlePrivacyProtectAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::createMock(Dispatcher::class);

        $this->action = new NovaBulkUpdateDomainHandlePrivacyProtectAction(
            jobDispatcher: $this->dispatcher,
        );
    }

    #[Test]
    public function handleDispatchesJobsInDryRun(): void
    {
        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        $actionFields = $this->makeActionFields('domains_two.csv', dryRun: true);

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            '[DRY RUN] Dispatched 2 domain handle update jobs.',
            (string) $responseData['message'],
        );

        self::assertCount(2, $queuedJobs);

        self::assertEquals(
            new NovaUpdateDomainHandlePrivacyProtectJob(domainName: 'example-one.test', dryRun: true),
            $queuedJobs[0],
        );

        self::assertEquals(
            new NovaUpdateDomainHandlePrivacyProtectJob(domainName: 'example-two.test', dryRun: true),
            $queuedJobs[1],
        );
    }

    #[Test]
    public function handleDispatchesJobsForRealRun(): void
    {
        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        $actionFields = $this->makeActionFields('domains_one.csv', dryRun: false);

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            '[EXECUTED] Dispatched 1 domain handle update jobs.',
            (string) $responseData['message'],
        );

        self::assertCount(1, $queuedJobs);

        self::assertEquals(
            new NovaUpdateDomainHandlePrivacyProtectJob(domainName: 'example-three.test', dryRun: false),
            $queuedJobs[0],
        );
    }

    #[Test]
    public function handleLimit(): void
    {
        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        // domains_two.csv has 2 rows, limit to 1
        $actionFields = $this->makeActionFields('domains_two.csv', dryRun: false, limit: 1);

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            '[EXECUTED] Dispatched 1 domain handle update jobs.',
            (string) $responseData['message'],
        );

        self::assertCount(1, $queuedJobs);

        self::assertEquals(
            new NovaUpdateDomainHandlePrivacyProtectJob(domainName: 'example-one.test', dryRun: false),
            $queuedJobs[0],
        );
    }

    private function makeActionFields(string $filename, bool $dryRun, int $limit = 0): ActionFields
    {
        $csv = (string) file_get_contents(__DIR__ . '/data/' . $filename);

        return new ActionFields(
            new Collection([
                'dry-run' => $dryRun,
                'limit' => $limit,
                'csv_upload' => UploadedFile::fake()->createWithContent($filename, $csv),
            ]),
            new Collection(),
        );
    }
}
