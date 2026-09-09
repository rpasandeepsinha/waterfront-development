<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Email\Exceptions\FailedToFetchStatusException;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Infra\HubspotClient\EmailClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;
use Webmozart\Assert\Assert;

class FetchEmailStatusAction
{
    public function __construct(
        private readonly EmailClient $emailClient,
    ) {
    }

    /**
     * @throws FailedToFetchStatusException
     * @throws ExceptionInterface
     */
    public function execute(EmailHistory $emailHistory): void
    {
        try {
            Assert::string($emailHistory->hubspot_id);
            $result  = $this->emailClient->getEmailStatus($emailHistory->hubspot_id);

            $emailHistory->hubspot_status = $result->status->value;
            $emailHistory->last_result = (string) json_encode($result);
            $emailHistory->save();
        } catch (HubspotAuthenticationException|
            HubspotConflictException|
            HubspotThrottledException|
            HubspotUnexpectedResponseException|
            HubspotJsonException $exception
        ) {
            throw new FailedToFetchStatusException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
