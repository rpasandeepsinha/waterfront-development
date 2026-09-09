<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient;

use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\DTO\HubspotSubscriptionDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;

class SubscriptionClient
{
    public function __construct(
        private readonly HubspotCrmHttpClient $crm,
        private readonly NormalizerInterface&DenormalizerInterface $serializer,
        private readonly HubspotConfigDTO $config,
    ) {
    }

    /**
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     * @throws ExceptionInterface
     */
    public function create(HubspotSubscriptionDTO $subscriptionRequest): HubspotSubscriptionDTO
    {
        $context = new ObjectNormalizerContextBuilder()->withGroups('create')->toArray();

        $response = $this->crm->post(
            sprintf('objects/%s', $this->config->subscriptionObjectTypeId),
            [
                'properties' => $this->serializer->normalize($subscriptionRequest, context: $context),
            ],
        );

        return $this->serializer->denormalize($response, HubspotSubscriptionDTO::class);
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
    public function update(HubspotSubscriptionDTO $subscriptionRequest): array
    {
        $context = new ObjectNormalizerContextBuilder()->withGroups('update')->toArray();

        return $this->crm->patch(
            sprintf('objects/%s/%d', $this->config->subscriptionObjectTypeId, $subscriptionRequest->hubspotId),
            [
                'properties' => $this->serializer->normalize($subscriptionRequest, context: $context),
            ],
        );
    }

    /**
     * @param HubspotSubscriptionDTO[] $subscriptions
     *
     * @return HubspotSubscriptionDTO[]|null
     */
    public function createBatch(array $subscriptions): ?array
    {
        $context = new ObjectNormalizerContextBuilder()->withGroups('create')->toArray();
        $body = [
            'inputs' => array_map(fn (HubspotSubscriptionDTO $subscription) => (object) [
                'properties' => (object) $this->serializer->normalize($subscription, context: $context),
            ], $subscriptions),
        ];

        $response = $this->crm->post(sprintf('objects/%s/batch/create', $this->config->subscriptionObjectTypeId), $body);

        if (array_key_exists('status', $response) && $response['status'] !== 'COMPLETE') {
            return null;
        }

        $hubspotSubscriptions = [];

        foreach ($response['results'] ?? [] as $result) {
            $subscription = $this->serializer->denormalize($result['properties'], HubspotSubscriptionDTO::class);
            $subscription->hubspotId = $result['id'];

            $hubspotSubscriptions[] = $subscription;
        }

        return $hubspotSubscriptions;
    }

    /**
     * @param HubspotSubscriptionDTO[] $subscriptions
     */
    public function updateBatch(array $subscriptions): void
    {
        $context = new ObjectNormalizerContextBuilder()->withGroups('update')->toArray();
        $body = [
            'inputs' => array_map(fn (HubspotSubscriptionDTO $subscription) => (object) [
                'id' => (string) $subscription->hubspotId,
                'properties' => (object) $this->serializer->normalize($subscription, context: $context),
            ], $subscriptions),
        ];

        $this->crm->post(sprintf('objects/%s/batch/update', $this->config->subscriptionObjectTypeId), $body);
    }

    /**
     * @param string[] $subscriptionUuids
     *
     * @return array{hubspotId: string, swUuid: string, otsStatus: ?string}[]
     */
    public function listByUuid(array $subscriptionUuids): array
    {
        $body = [
            'filterGroups' => [
                (object) [
                    'filters' => [
                        (object) [
                            'propertyName' => 'sw_uuid',
                            'operator' => 'IN',
                            'values' => $subscriptionUuids,
                        ],
                    ],
                ],
            ],
            'properties' => ['id', 'sw_uuid', 'ots_status'],
        ];

        $result = $this->crm->post(sprintf('objects/%s/search', $this->config->subscriptionObjectTypeId), $body);

        if (! array_key_exists('results', $result) || ! is_array($result['results'])) {
            return [];
        }

        /** @phpstan-ignore argument.type */
        return array_map(fn (array $result) => ['hubspotId' => $result['id'], 'swUuid' => $result['properties']['sw_uuid'], 'otsStatus' => $result['properties']['ots_status']], $result['results']);
    }
}
