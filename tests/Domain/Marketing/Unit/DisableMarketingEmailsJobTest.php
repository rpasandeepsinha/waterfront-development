<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Domain\Marketing\Jobs\DisableMarketingEmailsJob;
use Waterfront\Domain\Marketing\Models\HubspotEvent;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\Serializer\HubspotSerializerFactory;

#[CoversClass(DisableMarketingEmailsJob::class)]
class DisableMarketingEmailsJobTest extends TestCase
{
    #[Test]
    public function disableMarketingEmails(): void
    {
        $crmClient = $this->createMock(HubspotCrmHttpClient::class);
        $crmClient->expects(self::once())->method('post')->willReturn([
            'results' => [
                [
                    'id' => '1',
                    'properties' => [
                        'sw_uuid' => 'xxx-yyy-zzz',
                        'sw_customer_number'    => '123456789',
                        'email' => 'test@example.net',
                        'firstname' => 'john',
                        'lastname' => 'doe',
                        'marketing_opt_in' => 'true',
                    ],
                ],
            ],
        ]);
        $crmClient->expects(self::once())->method('patch')->with(
            self::callback(function ($uri) {
                self::assertSame('objects/contacts/1', $uri);
                return true;
            }),
            self::callback(fn ($body) => json_encode([
                    'properties' => [
                        'marketing_opt_in' => 'false',
                    ],
                ], JSON_THROW_ON_ERROR) === json_encode($body, JSON_THROW_ON_ERROR)),
        );

        $eventRepository = $this->createStub(HubspotEventRepository::class);
        $eventRepository->method('createPendingEvent')->willReturn(new HubspotEvent());

        $customer = new Customer([
            'uuid' => Uuid::uuid4()->toString(),
        ]);
        $customer->id = 1;

        $config = new HubspotConfigDTO(
            'accessToken',
            'https://localhost',
            'subscriptionObjectTypeId',
            'contactObjectTypeId',
            'marketing_mail_actions',
            'marketing_mail_surveys',
            'marketing_mail_newsletter',
            1,
            1
        );

        $job = new DisableMarketingEmailsJob($customer);
        $job->handle(
            new ContactsClient($crmClient, HubspotSerializerFactory::get(), $config),
            $eventRepository
        );
    }
}
