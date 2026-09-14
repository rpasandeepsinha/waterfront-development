<?php

declare(strict_types=1);

namespace Tests\Domain\Mailer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\HubspotMailer;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\EmailClient;

#[CoversClass(Mailer::class)]
#[CoversClass(HubspotMailer::class)]
#[CoversClass(EmailClient::class)]
class MailerPayloadTest extends IntegrationTestCase
{
    #[Test]
    public function sendEmailWithNonScalarPayload(): void
    {
        new TemplateFactory()->createOne([
            'slug' => 'template-string',
            'hubspot_template_id' => 'template-string',
        ]);

        $customer = new CustomerFactory()->createOne([
            'first_name' => 'Henk',
            'last_name' => 'Tank',
            'email' => 'henk.tank@example.com',
        ]);

        $order = ['extension' => 'bla.nl', 'nested' => ['a' => 'b', 'c' => 'd']];
        $total = '100.00';

        $mail = new readonly class($order, $total) implements MailTemplateInterface {
            /** @param mixed[] $order */
            public function __construct(
                /** mixed[] */
                public array $order,
                public string $total,
            ) {
            }

            public static function getTemplateSlug(): string
            {
                return 'template-string';
            }
        };

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

        $hubspotClient = self::createMock(HubspotCrmHttpClient::class);
        $hubspotClient
            ->expects(self::once())
            ->method('post')
            ->with(self::isString(), self::callback(function (array $payload) {
                self::assertArrayHasKey('customProperties', $payload);
                self::assertSame($payload['customProperties'], [
                    'order' => [
                        'nested' => [
                            'a' => 'b',
                            'c' => 'd',
                        ],
                        'extension' => 'bla.nl',
                    ],
                    'total' => '100.00',
                ]);

                return true;
            }))
            ->willReturn(json_decode($jsonResponse, true));
        $this->app->bind(HubspotCrmHttpClient::class, fn () => $hubspotClient);

        $mailer = self::resolve(MailerInterface::class);
        $mailer->send([$customer], $mail);
    }
}
