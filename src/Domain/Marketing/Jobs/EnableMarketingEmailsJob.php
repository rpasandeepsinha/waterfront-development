<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Jobs;

use Exception;
use Illuminate\Support\Sleep;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\Exceptions\CrmException;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotClientException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

/** @internal */
class EnableMarketingEmailsJob extends AbstractQueueableJob
{
    private const int CREATE_DELAY = 30;
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

    /**
     * @throws Exception
     */
    public function handle(
        ContactsClient $hubspotContactsClient,
        HubspotEventRepository $hubspotEventRepository,
        LoggerInterface $logger,
    ): void {
        // This mechanism of delaying the job is to prevent race conditions. The hubspot API has a slight delay on create actions.
        if ($hubspotEventRepository->wasRecentlyCreatedCustomer($this->customer)) {
            $logger->debug(sprintf('Customer: %s was recently created, cant upsert customer yet.', $this->customer->id));

            $this->release(self::CREATE_DELAY);
            return;
        }

        $event = $hubspotEventRepository->createPendingEvent($this->customer->id, 'Enable marketing emails');

        try {
            $contact = $hubspotContactsClient->findBySandwaveUuid($this->customer->uuid);
            if ($contact === null) {
                throw new CrmException(sprintf('Cannot enable marketing emails for customer (%s), contact not in hubspot.', $this->customer->uuid));
            }
            if (filter_var($contact->isAnonymized, FILTER_VALIDATE_BOOLEAN)) {
                throw new CrmException(sprintf('Cannot enable marketing emails for customer (%s), hubspot contact is anonymized.', $this->customer->uuid));
            }

            if (! filter_var($contact->marketingOptIn, FILTER_VALIDATE_BOOLEAN)) {
                $hubspotContactsClient->setMarketable($contact, true);
            }

            $hubspotEventRepository->markEventAsSuccessful($event);
        } catch (HubspotThrottledException) {
            $hubspotEventRepository->markEventAsFailed($event, 'API Rate limit reached, retrying...');

            // Sleep a while to let the API cooldown
            Sleep::sleep(5);

            $this->release(self::RATE_LIMIT_DELAY);
        } catch (HubspotClientException $exception) {
            $hubspotEventRepository->markEventAsFailed($event, sprintf('Communication with hubspot failed: %s', $exception->getMessage()));
        } catch (CrmException $exception) {
            $hubspotEventRepository->markEventAsFailed($event, sprintf('CRM Error: %s', $exception->getMessage()));
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
