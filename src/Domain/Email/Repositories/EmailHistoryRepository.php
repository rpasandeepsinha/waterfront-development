<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Mailer\IsMailable;
use Waterfront\Infra\HubspotClient\Enum\EmailSendStatus;

class EmailHistoryRepository
{
    public function createHistoryRecord(
        IsMailable $recipient,
        ReceiverType $receiverType,
        Template $template,
        string $ccEmails,
        ?string $payload = null,
        ?string $hubspotId = null,
    ): EmailHistory {
        $emailHistory = new EmailHistory();
        $emailHistory->sent_at = CarbonImmutable::now();
        $emailHistory->uuid = Uuid::uuid4()->toString();
        $emailHistory->receiver_email = $recipient->getEmail();
        $emailHistory->template_id = $template->id;
        $emailHistory->receiver_type = $receiverType;
        $emailHistory->receiver_uuid = $recipient->getUuid()?->toString() ?? Uuid::uuid4()->toString();
        $emailHistory->cc_emails = $ccEmails;
        $emailHistory->payload = $payload;
        $emailHistory->hubspot_id = $hubspotId;
        $emailHistory->hubspot_status = EmailSendStatus::PROCESSING->value;
        $emailHistory->requested_at = null;
        $emailHistory->save();

        return $emailHistory;
    }

    /** @return Collection<int, EmailHistory> */
    public function getBeforeDate(CarbonImmutable $beforeDate): Collection
    {
        return EmailHistory::where('requested_at', '<', $beforeDate)
            ->whereNotNull('payload')
            ->get();
    }

    public function removePayloadFromEmailHistory(EmailHistory $emailHistory): void
    {
        $emailHistory->payload = null;
        $emailHistory->save();
    }

    /** @return Collection<int, EmailHistory> */
    public function getByReceiverUuid(UuidInterface $uuid): Collection
    {
        return EmailHistory::where('receiver_uuid', $uuid)->get();
    }

    public function removeReceiverEmailAndPayload(EmailHistory $emailHistory): void
    {
        $emailHistory->receiver_email = null;
        $emailHistory->payload = null;
        $emailHistory->save();
    }

    public function getById(int $emailHistoryId): EmailHistory
    {
        return EmailHistory::findOrFail($emailHistoryId);
    }

    public function wasEmailSentSince(
        ReceiverType $receiverType,
        string $receiverUuid,
        int $templateId,
        CarbonImmutable $since
    ): bool {
        return EmailHistory::query()
            ->where('receiver_type', $receiverType->value)
            ->where('receiver_uuid', $receiverUuid)
            ->where('template_id', $templateId)
            ->where('sent_at', '>=', $since)
            ->exists();
    }
}
