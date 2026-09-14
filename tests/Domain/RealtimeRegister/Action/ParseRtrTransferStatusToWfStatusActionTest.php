<?php

declare(strict_types=1);

namespace Tests\Domain\RealtimeRegister\Action;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Action\ParseRtrTransferStatusToWfStatusAction;
use Waterfront\Infra\RtrClient\Services\Enums\LogStatus;

#[CoversClass(ParseRtrTransferStatusToWfStatusAction::class)]
class ParseRtrTransferStatusToWfStatusActionTest extends TestCase
{
    private ParseRtrTransferStatusToWfStatusAction $parseRtrTransferStatusToWfStatusAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parseRtrTransferStatusToWfStatusAction = new ParseRtrTransferStatusToWfStatusAction();
    }

    #[DataProvider('RtrTransferStatusProvider')]
    #[Test]
    public function getRtrErrorFromMessage(string $expectedTechnicalStatus, string $rtrStatus): void
    {
        self::assertSame(
            $expectedTechnicalStatus,
            $this->parseRtrTransferStatusToWfStatusAction->execute($rtrStatus),
        );
    }

    public static function RtrTransferStatusProvider(): Generator
    {
        yield [TechnicalStatus::PENDING->value, LogStatus::STATUS_PENDINGWHOIS];
        yield [TechnicalStatus::PENDING->value, LogStatus::STATUS_PENDING_APPROVAL_AUTHORIZED_CONTACT];
        yield [TechnicalStatus::PENDING->value, LogStatus::STATUS_PENDINGVALIDATION];
        yield [TechnicalStatus::PENDING->value, LogStatus::STATUS_PENDING];
        yield [TechnicalStatus::FAILED->value, LogStatus::STATUS_CANCELLED];
        yield [TechnicalStatus::FAILED->value, LogStatus::STATUS_REJECTED];
        yield [TechnicalStatus::FAILED->value, LogStatus::STATUS_FAILED];
        yield [TechnicalStatus::OK->value, LogStatus::STATUS_APPROVED];
        yield [TechnicalStatus::OK->value, LogStatus::STATUS_COMPLETED];
    }
}
