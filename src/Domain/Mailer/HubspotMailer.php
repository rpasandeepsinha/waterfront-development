<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Carbon\CarbonImmutable;
use JsonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Infra\HubspotClient\DTO\HubspotSendEmailRequest;
use Waterfront\Infra\HubspotClient\EmailClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;
use Waterfront\Infra\HubspotClient\ValueObject\EmailAddress;
use Webmozart\Assert\Assert;

class HubspotMailer
{
    public function __construct(
        private readonly EmailClient $emailClient,
        private readonly EmailHistoryRepository $emailHistoryRepository,
        private readonly PayloadDeserializer $payloadDeserializer,
    ) {
    }

    /**
     * @throws HubspotUnexpectedResponseException
     * @throws JsonException
     * @throws HubspotJsonException
     * @throws HubspotThrottledException
     * @throws ExceptionInterface
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     */
    public function send(int $emailHistoryId): void
    {
        $emailHistory = $this->emailHistoryRepository->getById($emailHistoryId);

        Assert::string($emailHistory->receiver_email);
        Assert::string($emailHistory->template->hubspot_template_id);

        $cc = $this->getCC($emailHistory);
        $ccAddresses = array_map(fn (string $email) => new EmailAddress($email), $cc);

        $payload = $emailHistory->payload !== null
            ? $this->payloadDeserializer->deserialize($emailHistory->payload)
            : null;

        $customProperties = $payload !== null
            ? array_map(fn (mixed $value) => is_scalar($value) ? (string) $value : $value, $payload)
            : [];
        $emailRequest = new HubspotSendEmailRequest(
            new EmailAddress($emailHistory->receiver_email),
            $emailHistory->template->hubspot_template_id,
            (string) $emailHistory->id,
            null,
            $ccAddresses,
            null,
            $customProperties,
        );

        $result = $this->emailClient->send($emailRequest);

        $emailHistory->hubspot_id = $result->statusId;
        $emailHistory->hubspot_status = $result->status->value;
        $emailHistory->requested_at = CarbonImmutable::now();
        $emailHistory->save();
    }

    /**
     * @return string[]
     */
    private function getCC(EmailHistory $emailHistory): array
    {
        if (! is_string($emailHistory->cc_emails) || $emailHistory->cc_emails === '') {
            return [];
        }

        if (! str_contains($emailHistory->cc_emails, ',')) {
            return [$emailHistory->cc_emails];
        }

        return explode(',', $emailHistory->cc_emails);
    }
}
