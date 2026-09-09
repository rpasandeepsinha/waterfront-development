<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient;

use Illuminate\Support\Arr;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\DTO\HubspotContactRequestDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;
use Webmozart\Assert\Assert;

class ContactsClient
{
    /**
     * We ask hubspot to include these properties in the responses.
     *
     * @var string[]
     */
    private const array PROPERTIES = [
        'sw_uuid',
        'sw_customer_number',
        'firstname',
        'lastname',
        'email',
        'company',
        'phone',
        'sw_street_name',
        'sw_street_number',
        'zip',
        'city',
        'state',
        'sw_country_code',
        'marketing_opt_in',
        'anonymized_by_customer',
        'anonymized_by_hubspot',
        'sw_create_date',
    ];

    public function __construct(
        private readonly HubspotCrmHttpClient $crm,
        private readonly NormalizerInterface&DenormalizerInterface $serializer,
        private readonly HubspotConfigDTO $config,
    ) {
    }

    /**
     * You can use this endpoint to find a contact for a sandwave customer.
     *
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     */
    public function findBySandwaveUuid(UuidInterface $uuid): ?HubspotContactRequestDTO
    {
        $response = $this->crm->post(
            uri: 'objects/contacts/search',
            body: [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'value' => $uuid->toString(),
                                'propertyName' => 'sw_uuid',
                                'operator' => 'EQ',
                            ],
                        ],
                    ],
                ],
                'properties' => $this->getDynamicHubspotProperties(),
            ],
        );
        $result = Arr::get($response, 'results.0');

        if (! is_array($result)) {
            return null;
        }

        return $this->serializer->denormalize($result, HubspotContactRequestDTO::class);
    }

    /**
     * You can use this endpoint to find a contact by email address.
     * Email address is a unique property in HubSpot.
     *
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     */
    public function findByEmailAddress(string $emailAddress): ?HubspotContactRequestDTO
    {
        $response = $this->crm->post(
            uri: 'objects/contacts/search',
            body: [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'value' => $emailAddress,
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                            ],
                        ],
                    ],
                ],
                'properties' => $this->getDynamicHubspotProperties(),
            ],
        );
        $result = Arr::get($response, 'results.0');

        if (! is_array($result)) {
            return null;
        }

        return $this->serializer->denormalize($result, HubspotContactRequestDTO::class);
    }

    /**
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     *
     * @return array<mixed>
     */
    public function create(HubspotContactRequestDTO $contactRequest): array
    {
        return $this->crm->post('objects/contacts', [
            'properties' => $this->serializer->normalize($contactRequest),
        ]);
    }

    /**
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     *
     * @return array<mixed>
     */
    public function update(HubspotContactRequestDTO $contact, HubspotContactRequestDTO $contactRequest): array
    {
        Assert::string($contactRequest->id);

        $context = new ObjectNormalizerContextBuilder()->withGroups('update')->toArray();

        return $this->crm->patch(
            sprintf('objects/contacts/%d', $contact->id),
            [
                'properties' => $this->serializer->normalize($contactRequest, context: $context),
            ],
        );
    }

    /**
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     */
    public function anonymize(HubspotContactRequestDTO $contactRequest): void
    {
        Assert::string($contactRequest->id);

        $context = new ObjectNormalizerContextBuilder()->withGroups('update')->toArray();

        $this->crm->patch(
            sprintf('objects/contacts/%d', $contactRequest->id),
            [
                'properties' => $this->serializer->normalize($contactRequest, context: $context),
            ],
        );
    }

    /**
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     */
    public function setMarketable(
        HubspotContactRequestDTO $contactDTO,
        bool $enabled,
    ): void {
        $this->crm->patch(
            sprintf('objects/contacts/%d', $contactDTO->id),
            [
                'properties' => [
                    'marketing_opt_in' => $enabled ? 'true' : 'false',
                ],
            ],
        );
    }

    /**
     * @return array<string>
     */
    private function getDynamicHubspotProperties(): array
    {
        $arr = self::PROPERTIES;

        $arr[] = $this->config->marketingMailActions;
        $arr[] = $this->config->marketingMailSurveys;
        $arr[] = $this->config->marketingMailNewsletter;

        return $arr;
    }
}
