<?php

declare(strict_types=1);

namespace Tests\Infra\HubspotClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotSendEmailRequest;
use Waterfront\Infra\HubspotClient\EmailClient;
use Waterfront\Infra\HubspotClient\Serializer\HubspotSerializerFactory;
use Waterfront\Infra\HubspotClient\ValueObject\EmailAddress;

#[CoversClass(EmailClient::class)]
class EmailClientTest extends IntegrationTestCase
{
    #[Test]
    public function sendParsesResponse(): void
    {
        $email = new HubspotSendEmailRequest(
            new EmailAddress('bla@bla.nla'),
            '187649851611',
            '1',
            null,
            null,
            null,
            null,
        );

        $jsonResponse = <<<JSON
{
  "eventId": {
    "created": "2024-10-07T13:00:56.048Z",
    "id": "3fa85f64-5717-4562-b3fc-2c963f66afa6"
  },
  "completedAt": "2024-10-07T13:00:56.048Z",
  "statusId": "string",
  "sendResult": "SENT",
  "requestedAt": "2024-10-07T13:00:56.048Z",
  "startedAt": "2024-10-07T13:00:56.048Z",
  "status": "PENDING"
}
JSON;

        $mockHubspotClient = $this->createMock(HubspotCrmHttpClient::class);
        $mockHubspotClient->expects(self::once())
            ->method('post')
            ->with(
                '/marketing/v4/email/single-send',
            )
            ->willReturn(json_decode($jsonResponse, true));

        $x = new EmailClient($mockHubspotClient, HubspotSerializerFactory::get());
        $x->send($email);
    }
}
