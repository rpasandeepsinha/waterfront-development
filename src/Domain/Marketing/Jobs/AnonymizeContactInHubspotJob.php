<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Jobs;

use Exception;
use Illuminate\Support\Sleep;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\Factory\HubspotContactFactory;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotClientException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

/** @internal */
class AnonymizeContactInHubspotJob extends AbstractQueueableJob
{
    private const int RATE_LIMIT_DELAY = 120;

    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * This prevents secondly limit errors.
     */
    public int $backoff = 3;

    public function __construct(private readonly Customer $customer)
    {
        parent::__construct();
    }

    public function handle(
        ContactsClient $hubspotContactsClient,
        HubspotEventRepository $hubspotEventRepository,
        HubspotContactFactory $contactFactory
    ): void {
        $event = $hubspotEventRepository->createPendingEvent($this->customer->id, 'Anonymizing customer in Hubspot');

        try {
            $contactDTO = $hubspotContactsClient->findBySandwaveUuid($this->customer->uuid);

            if ($contactDTO === null) {
                $hubspotEventRepository->markEventAsSuccessful($event, 'Contact not in hubspot. Nothing to anonymize');
                return;
            }

            $contactDTO = $contactFactory->buildForHubspotRequest(
                customer: $this->customer,
                isAnonymized: true,
                hasDirectDebit: false,
                id: $contactDTO->id,
                marketingOptIn: 'false',
            );

            $hubspotContactsClient->anonymize($contactDTO);

            $hubspotEventRepository->markEventAsSuccessful($event, 'Anonymized existing Hubspot contact');
        } catch (HubspotThrottledException) {
            $hubspotEventRepository->markEventAsFailed($event, 'API Rate limit reached, retrying...');

            // Sleep a while to let the API cooldown
            Sleep::sleep(5);

            $this->release(self::RATE_LIMIT_DELAY);
        } catch (HubspotClientException $exception) {
            $hubspotEventRepository->markEventAsFailed($event, sprintf('Communication with hubspot failed: %s', $exception->getMessage()));
        } catch (Exception $exception) {
            $hubspotEventRepository->markEventAsFailed($event, sprintf('Unknown error: %s', $exception->getMessage()));
            throw $exception;
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CRM;
    }
}
