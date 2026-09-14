<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Acronis\Actions;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Acronis\Actions\NovaVerifyAcronisProviderAction;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Infra\AcronisClient\DTO\Applications\Application;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaVerifyAcronisProviderAction::class)]
class NovaVerifyAcronisProviderActionTest extends IntegrationTestCase
{
    private AcronisProvider $provider;

    private backupService&MockObject $backupService;

    private LoggerInterface&Stub $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = AcronisProviderFactory::new()->createOne();
        $this->backupService = self::createMock(BackupService::class);
        $this->logger = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function handleReturnsSuccess(): void
    {
        $acronisId = Uuid::uuid4()->toString();

        $this->backupService
            ->expects(self::once())
            ->method('getApplicationListFromProvider')
            ->with($this->provider)
            ->willReturn(new ApplicationsList([
                new Application(
                    id: $acronisId,
                    name: 'name',
                    type: 'type',
                    usages: ['usage', 'array'],
                    apiBaseUrl: 'url',
                ),
            ]));

        $action = new NovaVerifyAcronisProviderAction(
            translator: self::resolve(TranslatorInterface::class),
            logger: $this->logger,
            backupService: $this->backupService,
        );

        $result = $action->handle(
            new ActionFields(new Collection([]), new Collection([])),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);

        $array = $result->jsonSerialize();
        self::assertArrayHasKey('modal', $array);

        $modal = $array['modal'];
        self::assertInstanceOf(Modal::class, $modal);

        self::assertSame('nova-action.acronis-provider.verify.success', $modal->payload['title']);
        self::assertIsString($modal->payload['code']);
        self::assertStringContainsString(sprintf('"id": "%s"', $acronisId), $modal->payload['code']);
    }

    #[Test]
    public function handleReturnsErrorWhenThrowsException(): void
    {
        $exception = new Exception('request failed');

        $this->backupService
            ->expects(self::once())
            ->method('getApplicationListFromProvider')
            ->with($this->provider)
            ->willThrowException($exception);

        $action = new NovaVerifyAcronisProviderAction(
            translator: self::resolve(TranslatorInterface::class),
            logger: $this->logger,
            backupService: $this->backupService,
        );

        $result = $action->handle(
            new ActionFields(new Collection([]), new Collection([])),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.acronis-provider.verify.failure => Exception', $danger->text);
    }
}
