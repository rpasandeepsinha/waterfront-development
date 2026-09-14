<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Integration;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Marketing\Factory\HubspotContactFactory;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Domain\Marketing\Jobs\AnonymizeContactInHubspotJob;
use Waterfront\Domain\Marketing\Models\HubspotEvent;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\Serializer\HubspotSerializerFactory;

#[CoversClass(AnonymizeContactInHubspotJob::class)]
class AnonymizeCustomerInHubspotJobTest extends IntegrationTestCase
{
    #[Test]
    public function anonymizeCustomer(): void
    {
        $customer = new CustomerFactory()->createOne([
            'email' => 'anonymized.customer@sandwave.io',
            'first_name' => 'anonymized-first_name',
            'last_name' => 'anonymized-last_name',
            'organization' => '',
        ]);

        new CustomerAddressFactory()->for($customer)->create([
            'street_name' => 'anonymized-street',
            'street_number' => '1',
            'street_number_addition' => 'B',
            'zip_code' => '1234AB',
            'city' => 'anonymized-city',
            'country_code' => 'NL',
        ]);

        $crmClient = $this->createMock(HubspotCrmHttpClient::class);
        $crmClient
            ->expects(self::once())
            ->method('post')
            ->with('objects/contacts/search', Assert::anything())
            ->willReturn(
                [
                    'results' => [
                        [
                            'id' => '123',
                            'properties' => [
                                'company' => 'Biglytics',
                                'createdate' => '2019-10-30T03:30:17.883Z',
                                'email' => 'bcooper@biglytics.net',
                                'firstname' => 'Bryan',
                                'lastmodifieddate' => '2019-12-07T16:50:06.678Z',
                                'lastname' => 'Cooper',
                                'phone' => '(877) 929-0687',
                                'website' => 'biglytics.net',
                                'sw_uuid' => 'xxx-yyy-zzz',
                                'sw_customer_number' => '123456789',
                                'marketing_opt_in' => 'false',
                                'anonymized_by_customer' => 'false',
                                'anonymized_by_hubspot' => 'false',
                                'sw_street_name' => 'Big street',
                                'sw_street_number' => '12',
                                'sw_street_number_addition' => 'B',
                                'zip' => '1234AB',
                                'city' => 'Big city',
                                'state' => 'Big state',
                                'sw_country_code' => 'NL',
                                'sw_has_direct_debit' => 'false',
                                'is_migrated' => 'false',
                            ],
                        ],
                    ],
                ],
            );
        $crmClient
            ->expects(self::once())
            ->method('patch')
            ->with('objects/contacts/123', self::callback(function ($body) use ($customer) {
                self::assertEqualsCanonicalizing(
                    [
                        'properties' => [
                            'email' => $customer->email,
                            'firstname' => $customer->first_name,
                            'lastname' => $customer->last_name,
                            'company' => $customer->organization,
                            'phone' => $customer->phone_number,
                            'marketing_opt_in' => 'false',
                            'anonymized_by_customer' => 'true',
                            'sw_create_date' => $customer->customer_since->toDateString(),
                            'sw_street_name' => $customer->address?->street_name,
                            'sw_street_number' => $customer->address?->street_number,
                            'sw_street_number_addition' => $customer->address?->street_number_addition,
                            'zip' => $customer->address?->zip_code,
                            'city' => $customer->address?->city,
                            'sw_country_code' => $customer->address?->country_code,
                            'sw_has_direct_debit' => 'false',
                            'sw_uuid' => $customer->uuid->toString(),
                            'sw_customer_number' => (string) $customer->customer_number,
                            'is_migrated' => 'false',
                        ],
                    ],
                    $body,
                );

                return true;
            }));

        $eventRepository = $this->createStub(HubspotEventRepository::class);
        $eventRepository->method('createPendingEvent')->willReturn(new HubspotEvent());

        $config = new HubspotConfigDTO(
            'accessToken',
            'https://localhost',
            'subscriptionObjectTypeId',
            'contactObjectTypeId',
            'marketing_mail_actions',
            'marketing_mail_surveys',
            'marketing_mail_newsletter',
            1,
            1,
        );

        $job = new AnonymizeContactInHubspotJob($customer);
        $job->handle(
            new ContactsClient($crmClient, HubspotSerializerFactory::get(), $config),
            $eventRepository,
            new HubspotContactFactory(),
        );
    }
}
