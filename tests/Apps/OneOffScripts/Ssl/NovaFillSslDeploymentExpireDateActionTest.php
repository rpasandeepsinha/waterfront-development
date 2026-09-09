<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Ssl;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Ssl\NovaFillSslDeploymentExpireDateAction;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;

#[CoversClass(NovaFillSslDeploymentExpireDateAction::class)]
class NovaFillSslDeploymentExpireDateActionTest extends IntegrationTestCase
{
    private LoggerInterface $logger;

    private MockObject&Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createStub(LoggerInterface::class);
        $this->dispatcher = self::createMock(Dispatcher::class);
    }

    #[Test]
    public function handleDebugReturnsCountNoJobsQueued(): void
    {
        $builder = self::mock(Builder::class);
        $builder->shouldReceive('count')->andReturn(2);

        $sslDeploymentRepository = self::createStub(DeploymentRepository::class);
        $sslDeploymentRepository->method('getExpireDateBackfillCandidates')->willReturn($builder);

        $this->dispatcher->expects(self::never())->method('dispatch');

        $novaFillSslDeploymentExpireDateAction = new NovaFillSslDeploymentExpireDateAction(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            sslDeploymentRepository: $sslDeploymentRepository,
        );

        $actionFields = new ActionFields(
            new Collection(['debug' => true, 'limit' => 10]),
            new Collection()
        );

        $actionResponse = $novaFillSslDeploymentExpireDateAction->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);

        self::assertSame('Debug: 2 SSL deployment(s) missing expire_date.', (string) $responseData['message']);
    }

    #[Test]
    public function handleQueuedJobsHonorsLimit(): void
    {
        $sslDeployments = new Collection([
            (function (): SslDeployment {
                $sslDeployment = new SslDeployment();
                $sslDeployment->id = 10;
                return $sslDeployment;
            })(),
            (function (): SslDeployment {
                $sslDeployment = new SslDeployment();
                $sslDeployment->id = 11;
                return $sslDeployment;
            })(),
            (function (): SslDeployment {
                $sslDeployment = new SslDeployment();
                $sslDeployment->id = 12;
                return $sslDeployment;
            })(),
        ]);

        $builder = self::mock(Builder::class);

        $builder->shouldReceive('orderBy')->with('id')->andReturnSelf();

        $builder->shouldReceive('chunkById')
            ->with(
                500,
                self::isCallable()
            )
            ->andReturnUsing(static function (int $chunkSize, callable $callback) use ($sslDeployments): void {
                $callback($sslDeployments);
            });

        $sslDeploymentRepository = self::createStub(DeploymentRepository::class);
        $sslDeploymentRepository->method('getExpireDateBackfillCandidates')->willReturn($builder);

        $this->dispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static fn ($queuedJob): bool => $queuedJob instanceof UpdateSslExpireDate));

        $novaFillSslDeploymentExpireDateAction = new NovaFillSslDeploymentExpireDateAction(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            sslDeploymentRepository: $sslDeploymentRepository,
        );

        $actionFields = new ActionFields(
            new Collection(['debug' => false, 'limit' => 2]),
            new Collection()
        );

        $actionResponse = $novaFillSslDeploymentExpireDateAction->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);

        $messageText = (string) $responseData['message'];
        self::assertSame('Queued 2 UpdateSslExpireDate job(s).', $messageText);
    }
}
