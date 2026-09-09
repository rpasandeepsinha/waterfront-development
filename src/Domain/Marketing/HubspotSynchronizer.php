<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing;

use AssertionError;
use Carbon\CarbonImmutable;
use Illuminate\Log\Logger;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Marketing\Enums\HubspotObjectType;
use Waterfront\Domain\Marketing\Factory\HubspotSubscriptionFactory;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Domain\Marketing\Models\HubspotObjectSync;
use Waterfront\Domain\Marketing\Repositories\HubspotRepository;
use Waterfront\Domain\OneTimeServices\Repositories\OneTimeServiceRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\DTO\HubspotSubscriptionDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotClientException;
use Waterfront\Infra\HubspotClient\SubscriptionClient;
use Waterfront\Support\Enums\LoggingContextKeys;

class HubspotSynchronizer
{
    public function __construct(
        private readonly HubspotRepository $hubspotRepository,
        private readonly SubscriptionClient $subscriptionClient,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly OneTimeServiceRepository $oneTimeServiceRepository,
        private readonly HubspotSubscriptionFactory $hubspotSubscriptionFactory,
        private readonly HubspotEventRepository $hubspotEventRepository,
        private readonly Logger $logger,
        private readonly HubspotConfigDTO $config,
    ) {
    }

    public function runSync(): void
    {
        $customerIds = $this->hubspotRepository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        foreach ($customerIds as $customerId) {
            $subscriptions = $this->subscriptionRepository->findAllByCustomerId($customerId->id);
            $otsSubscriptions = $this->oneTimeServiceRepository->findAllByCustomerId($customerId->id);

            $hubspotCreateDtos = [];
            $hubspotUpdateDtos = [];

            foreach ($subscriptions as $subscription) {
                $subscription->loadMissing(['customer', 'product', 'orderLineItem']);

                $hubspotSync = $this->hubspotRepository->getObjectBySubscriptionUuid(Uuid::fromString($subscription->uuid));
                $data = $this->hubspotSubscriptionFactory->createDTOFromSubscription($subscription);

                // Switch contact triggers a workflow in Hubspot that request contact information from WF using a webhook.
                $data->switchContact = true;

                if ($hubspotSync !== null) {
                    $data->hubspotId = $hubspotSync->hubspot_object_id;
                    $hubspotUpdateDtos[] = $data;
                    continue;
                }
                $hubspotCreateDtos[] = $data;
            }

            foreach ($otsSubscriptions as $otsSubscription) {
                $hubspotSync = $this->hubspotRepository->getObjectBySubscriptionUuid($otsSubscription->uuid);
                $data = $this->hubspotSubscriptionFactory->createDTOFromOneTimeService($otsSubscription);

                // Switch contact triggers a workflow in Hubspot that request contact information from WF using a webhook.
                $data->switchContact = true;

                if ($hubspotSync !== null) {
                    $data->hubspotId = $hubspotSync->hubspot_object_id;
                    $hubspotUpdateDtos[] = $data;
                    continue;
                }
                $hubspotCreateDtos[] = $data;
            }

            $this->createBatch($hubspotCreateDtos, $customerId->id);
            $this->updateBatch($hubspotUpdateDtos, $customerId->id);
        }
    }

    /**
     * @param HubspotSubscriptionDTO[] $hubspotCreateDtos
     */
    private function createBatch(array $hubspotCreateDtos, int $customerId): void
    {
        if (count($hubspotCreateDtos) === 0) {
            return;
        }
        $event = $this->hubspotEventRepository->createPendingEvent($customerId, 'batch create subscriptions');

        $batches = array_chunk($hubspotCreateDtos, $this->config->createBatchSize);
        foreach ($batches as $batch) {
            try {
                $createResult = $this->subscriptionClient->createBatch($batch);

                if ($createResult === null) {
                    $this->logger->error(sprintf('create subscription batch with count: %s empty result', count($hubspotCreateDtos)));
                    $this->hubspotEventRepository->markEventAsFailed($event, 'Failed to batch create subscriptions');
                    return;
                }

                $this->parseCreateResult($createResult);
            } catch (HubspotClientException $exception) {
                $this->logger->error(
                    sprintf(
                        'hubspotClientException for  create subscription batch with count: %s',
                        count($hubspotCreateDtos)
                    ),
                    [LoggingContextKeys::EXCEPTION => $exception]
                );
                $this->hubspotEventRepository->markEventAsFailed($event, $exception->getMessage());
                return;
            }
        }

        $this->hubspotEventRepository->markEventAsSuccessful($event, 'create subscription batch completed');
    }

    /**
     * @param HubspotSubscriptionDTO[] $createResult
     */
    private function parseCreateResult(array $createResult): void
    {
        foreach ($createResult as $hubspotObject) {
            try {
                $syncObject = new HubspotObjectSync();
                assert(is_string($hubspotObject->hubspotId));
                $syncObject->hubspot_object_id = $hubspotObject->hubspotId;
                assert(is_string($hubspotObject->uuid));
                $syncObject->sandwave_object_id = Uuid::fromString($hubspotObject->uuid);
                $syncObject->sandwave_object_type = $hubspotObject->otsStatus === null ? HubspotObjectType::SUBSCRIPTION : HubspotObjectType::ONE_TIME_SUBSCRIPTION;
                $syncObject->synced_at = CarbonImmutable::now();
                $syncObject->save();
            } catch (AssertionError $e) {
                $this->logger->error('failed to insert hubspotSyncObject', [LoggingContextKeys::EXCEPTION => $e]);
                continue;
            }
        }
    }

    /**
     * @param HubspotSubscriptionDTO[] $hubspotUpdateDtos
     */
    private function updateBatch(array $hubspotUpdateDtos, int $customerId): void
    {
        if (count($hubspotUpdateDtos) === 0) {
            return;
        }

        $event = $this->hubspotEventRepository->createPendingEvent($customerId, 'batch update subscriptions');

        $batches = array_chunk($hubspotUpdateDtos, $this->config->updateBatchSize);

        foreach ($batches as $batch) {
            try {
                $this->subscriptionClient->updateBatch($batch);
                $this->parseUpdate($batch);
            } catch (HubspotClientException $exception) {
                $this->logger->error(
                    sprintf(
                        'hubspotClientException for  update subscription batch with count: %s',
                        count($hubspotUpdateDtos)
                    ),
                    [LoggingContextKeys::EXCEPTION => $exception]
                );
                $this->hubspotEventRepository->markEventAsFailed($event, $exception->getMessage());
                return;
            }
        }

        $this->hubspotEventRepository->markEventAsSuccessful($event, 'Success to batch synced subscriptions');
    }

    /**
     * @param HubspotSubscriptionDTO[] $batch
     */
    private function parseUpdate(array $batch): void
    {
        foreach ($batch as $hubspotUpdateDto) {
            assert(is_string($hubspotUpdateDto->uuid));
            try {
                $this->hubspotRepository->updateSyncedAt(Uuid::fromString($hubspotUpdateDto->uuid));
            } catch (AssertionError $e) {
                $this->logger->error(
                    sprintf('failed to update hubspotSyncObject with uuid %s', $hubspotUpdateDto->uuid),
                    [LoggingContextKeys::EXCEPTION => $e]
                );
                continue;
            }
        }
    }
}
