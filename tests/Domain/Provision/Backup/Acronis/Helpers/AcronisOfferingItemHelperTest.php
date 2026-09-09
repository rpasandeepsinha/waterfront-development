<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Acronis\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\OfferingItemPropertyName;
use Waterfront\Domain\Provision\Backup\Acronis\Helpers\AcronisOfferingItemHelper;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;

#[CoversClass(AcronisOfferingItemHelper::class)]
class AcronisOfferingItemHelperTest extends TestCase
{
    private AcronisOfferingItemHelper $acronisOfferingItemHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acronisOfferingItemHelper = new AcronisOfferingItemHelper();
    }

    #[Test]
    public function getOfferingItemsWithEverythingSetToNull(): void
    {
        $request = new UpdateBackupRequest(
            tagUuid: Uuid::uuid4(),
        );

        $data = $this->acronisOfferingItemHelper->getOfferingItemDto($request);
        self::assertCount(0, $data);
    }

    #[Test]
    public function offeringItemWithGoogleWorkspaceSetToNull(): void
    {
        $request = new UpdateBackupRequest(
            tagUuid: Uuid::uuid4(),
        );

        $data = $this->acronisOfferingItemHelper->getOfferingItemDto($request);
        self::assertCount(0, $data);
    }

    #[Test]
    public function offeringItemWithGoogleWorkspaceSetToTrue(): void
    {
        $request = new UpdateBackupRequest(
            tagUuid: Uuid::uuid4(),
            enableGoogleWorkspaceDrive: true,
        );

        $data = $this->acronisOfferingItemHelper->getOfferingItemDto($request);
        self::assertCount(1, $data);
        $dto = $data[0];
        self::assertSame(OfferingItemPropertyName::GOOGLE_TEAM_DRIVE, $dto->propertyName);
        self::assertNull($dto->quota);
        self::assertSame(OfferingItemStatus::ACTIVE, $dto->status);
    }

    #[Test]
    public function offeringItemWithGoogleWorkspaceSetToFalse(): void
    {
        $request = new UpdateBackupRequest(
            tagUuid: Uuid::uuid4(),
            enableGoogleWorkspaceDrive: false,
        );

        $data = $this->acronisOfferingItemHelper->getOfferingItemDto($request);
        self::assertCount(1, $data);
        $dto = $data[0];
        self::assertSame(OfferingItemPropertyName::GOOGLE_TEAM_DRIVE, $dto->propertyName);
        self::assertNull($dto->quota);
        self::assertSame(OfferingItemStatus::NOT_ACTIVE, $dto->status);
    }
}
