<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Acronis\Actions;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Acronis\Actions\NovaShowAcronisOfferingItemsForTenantAction;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaShowAcronisOfferingItemsForTenantAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaShowAcronisOfferingItemsForTenantActionTest extends IntegrationTestCase
{
    private AcronisProvider $provider;

    private BackupService&MockObject $backupService;

    private LoggerInterface&MockObject $logger;

    private string $tenantUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = AcronisProviderFactory::new()->createOne();
        $this->backupService = self::createMock(BackupService::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->tenantUuid = Uuid::uuid4()->toString();
    }

    #[Test]
    public function handleReturnsModalWithOfferingItemsOnSuccess(): void
    {
        $applicationId = Uuid::uuid4()->toString();

        $offeringItem = new OfferingItem(
            applicationId: $applicationId,
            name: 'item-name',
            tenantId: $this->tenantUuid,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: null,
        );
        $offeringItem->usageName = 'usage-1';
        $offeringItem->type = OfferingItemType::FEATURE;
        $offeringItem->measurementUnit = MeasurementUnit::QUANTITY;

        $offeringItems = new OfferingItems(
            checkUsage: null,
            offeringItems: null,
            items: [$offeringItem],
        );

        $this->backupService
            ->expects(self::once())
            ->method('getOfferingItemsForProviderByTenant')
            ->with($this->provider, $this->tenantUuid)
            ->willReturn($offeringItems);

        $action = $this->makeAction();

        $result = $action->handle(
            $this->getTenantUuidActionFields(),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);

        $array = $result->jsonSerialize();
        self::assertArrayHasKey('modal', $array);

        $modal = $array['modal'];
        self::assertInstanceOf(Modal::class, $modal);

        self::assertSame('nova-action.acronis-provider.offering-items.success', $modal->payload['title']);
        self::assertIsString($modal->payload['code']);
        self::assertStringContainsString(sprintf('"tenant_id": "%s"', $this->tenantUuid), $modal->payload['code']);
        self::assertStringContainsString(sprintf('"application_id": "%s"', $applicationId), $modal->payload['code']);
    }

    #[Test]
    public function handleReturnsDangerWhenClientThrowsException(): void
    {
        $exception = new Exception('boom');

        $this->backupService
            ->expects(self::once())
            ->method('getOfferingItemsForProviderByTenant')
            ->with($this->provider, $this->tenantUuid)
            ->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $action = $this->makeAction();

        $result = $action->handle(
            $this->getTenantUuidActionFields(),
            new Collection([$this->provider]),
        );

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.acronis-provider.offering-items.failure => Exception', $danger->text);
    }

    private function makeAction(): NovaShowAcronisOfferingItemsForTenantAction
    {
        return new NovaShowAcronisOfferingItemsForTenantAction(
            translator: self::resolve(TranslatorInterface::class),
            logger: $this->logger,
            backupService: $this->backupService,
        );
    }

    private function getTenantUuidActionFields(): ActionFields
    {
        // @phpstan-ignore-next-line
        return new ActionFields(new Collection(['tenant_uuid' => $this->tenantUuid]), new Collection([]));
    }
}
