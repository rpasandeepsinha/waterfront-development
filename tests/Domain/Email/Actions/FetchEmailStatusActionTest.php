<?php

declare(strict_types=1);

namespace Tests\Domain\Email\Actions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\EmailHistoryFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Email\Actions\FetchEmailStatusAction;
use Waterfront\Domain\Email\Exceptions\FailedToFetchStatusException;
use Waterfront\Infra\HubspotClient\DTO\HubspotSendEmailResponse;
use Waterfront\Infra\HubspotClient\EmailClient;
use Waterfront\Infra\HubspotClient\Enum\EmailSendResult;
use Waterfront\Infra\HubspotClient\Enum\EmailSendStatus;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;

#[CoversClass(FetchEmailStatusAction::class)]
class FetchEmailStatusActionTest extends IntegrationTestCase
{
    #[Test]
    public function fetchEmailStatusSuccess(): void
    {
        $emailHistory = new EmailHistoryFactory()
            ->withTemplate()
            ->createOne([
                'hubspot_status' => EmailSendStatus::PROCESSING->value,
            ]);

        $emailClient = self::createMock(EmailClient::class);
        $emailClient
            ->expects(self::once())
            ->method('getEmailStatus')
            ->with($emailHistory->hubspot_id)
            ->willReturn(new HubspotSendEmailResponse(
                Uuid::uuid4(),
                CarbonImmutable::now(),
                'bla',
                EmailSendStatus::COMPLETE,
                EmailSendResult::SENT,
                CarbonImmutable::now(),
                CarbonImmutable::now(),
                CarbonImmutable::now(),
            ));

        $action = new FetchEmailStatusAction($emailClient);
        $action->execute($emailHistory);

        $emailHistory->refresh();

        self::assertSame(EmailSendStatus::COMPLETE->value, $emailHistory->hubspot_status);
    }

    #[Test]
    public function fetchEmailStatusClientThrowsExceptionOnClientException(): void
    {
        $emailHistory = new EmailHistoryFactory()->createOne();

        $emailClient = self::createMock(EmailClient::class);
        $emailClient
            ->expects(self::once())
            ->method('getEmailStatus')
            ->with($emailHistory->hubspot_id)
            ->willThrowException(new HubspotUnexpectedResponseException());

        $action = new FetchEmailStatusAction($emailClient);
        $this->expectException(FailedToFetchStatusException::class);
        $action->execute($emailHistory);
    }
}
