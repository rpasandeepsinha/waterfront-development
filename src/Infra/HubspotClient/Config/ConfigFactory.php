<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Config;

use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;

readonly class ConfigFactory
{
    public function __construct(
        private ConfigurationInterface $configuration,
    ) {
    }

    public function get(): HubspotConfigDTO
    {
        return new HubspotConfigDTO(
            accessToken: $this->configuration->getAsString('services.hubspot.access_token'),
            baseUrl: 'https://api.hubapi.com/crm/v3/',
            subscriptionObjectTypeId: $this->configuration->getAsString('services.hubspot.subscription_object_type_id'),
            subscriptionContactId: $this->configuration->getAsString('services.hubspot.subscription_contact_id'),
            marketingMailActions: $this->configuration->getAsString('hubspot.marketing_mail_actions'),
            marketingMailSurveys: $this->configuration->getAsString('hubspot.marketing_mail_surveys'),
            marketingMailNewsletter: $this->configuration->getAsString('hubspot.marketing_mail_newsletter'),
            createBatchSize: 95,
            updateBatchSize: 95,
        );
    }
}
