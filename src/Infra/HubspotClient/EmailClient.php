<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotSendEmailRequest;
use Waterfront\Infra\HubspotClient\DTO\HubspotSendEmailResponse;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;

class EmailClient
{
    public function __construct(
        private readonly HubspotCrmHttpClient $crm,
        private readonly NormalizerInterface&DenormalizerInterface $serializer,
    ) {
    }

    /**
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     *
     * @see https://developers.hubspot.com/beta-docs/reference/api/marketing/emails/single-send-api
     */
    public function send(HubspotSendEmailRequest $email): HubspotSendEmailResponse
    {
        $body = $this->serializer->normalize($email);
        assert(is_array($body));

        $response = $this->crm->post(
            uri: '/marketing/v4/email/single-send',
            body: $body,
        );

        return $this->serializer->denormalize($response, HubspotSendEmailResponse::class);
    }

    /**
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     *
     * @see https://developers.hubspot.com/beta-docs/reference/api/marketing/emails/single-send-api
     */
    public function getEmailStatus(string $statusId): HubspotSendEmailResponse
    {
        $response = $this->crm->get(
            uri: sprintf('/marketing/v3/email/send-statuses/%s', $statusId),
        );

        return $this->serializer->denormalize($response, HubspotSendEmailResponse::class);
    }
}
