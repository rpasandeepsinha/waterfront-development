<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\Jobs\AnonymizeContactInHubspotJob;
use Waterfront\Domain\Marketing\Jobs\DisableMarketingEmailsJob;
use Waterfront\Domain\Marketing\Jobs\EnableMarketingEmailsJob;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;

class Crm
{
    public function __construct(
        private readonly ContactsClient $contactsClient,
        private readonly ConfigurationInterface $configuration,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function anonymizeCustomer(Customer $customer): void
    {
        if (! $this->hasValidHubSpotConfig()) {
            return;
        }

        $this->jobDispatcher->dispatch(new AnonymizeContactInHubspotJob($customer));
    }

    public function enableMarketingEmails(Customer $customer): void
    {
        if (! $this->hasValidHubSpotConfig()) {
            return;
        }

        $this->jobDispatcher->dispatch(new EnableMarketingEmailsJob($customer));
    }

    public function disableMarketingEmails(Customer $customer): void
    {
        if (! $this->hasValidHubSpotConfig()) {
            return;
        }

        $this->jobDispatcher->dispatch(new DisableMarketingEmailsJob($customer));
    }

    /**
     * Concerning enabling marketing emails as a whole.
     *
     * @throws HubspotJsonException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     */
    public function hasEnabledMarketingEmails(Customer $customer): bool
    {
        if (! $this->hasValidHubSpotConfig()) {
            return false;
        }

        $contact = $this->contactsClient->findBySandwaveUuid($customer->uuid);

        return $contact->isMarketable ?? false;
    }

    /**
     * Concerning opting into various types of marketing emails.
     *
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     * @throws HubspotJsonException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     *
     * @return array<string,bool>
     */
    public function hasOptedInMarketingEmails(Customer $customer): array
    {
        if (! $this->hasValidHubSpotConfig()) {
            return [
                'isOptedInMailActions' => false,
                'isOptedInMailSurveys' => false,
                'isOptedInMailNewsletter' => false,
            ];
        }

        $contact = $this->contactsClient->findBySandwaveUuid($customer->uuid);

        return [
            'isOptedInMailActions' => $contact->isOptedInMailActions ?? false,
            'isOptedInMailSurveys' => $contact->isOptedInMailSurveys ?? false,
            'isOptedInMailNewsletter' => $contact->isMarketable ?? false,
        ];
    }

    private function hasValidHubSpotConfig(): bool
    {
        $accessToken = $this->configuration->getAsString('services.hubspot.access_token');
        $objectId = $this->configuration->getAsString('services.hubspot.subscription_object_type_id');
        $contactId = $this->configuration->getAsString('services.hubspot.subscription_contact_id');

        return ! ($accessToken === '' || $objectId === '' || $contactId === '');
    }
}
