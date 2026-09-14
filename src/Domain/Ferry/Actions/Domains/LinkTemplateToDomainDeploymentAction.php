<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Domains;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Dto\Domains\DomainMigrationPayload;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;
use Waterfront\Support\Enums\LoggingContextKeys;

class LinkTemplateToDomainDeploymentAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(DomainDeployment $domainDeployment, DomainMigrationPayload $domainMigrationPayload): void
    {
        $referenceTemplateId = $domainMigrationPayload->referenceDnsTemplateId;
        if ($referenceTemplateId === null) {
            return;
        }

        /** @var MigratedDnsTemplate $migratedDnsTemplate */
        $migratedDnsTemplate = MigratedDnsTemplate::query()
            ->where('reference_template_id', $referenceTemplateId)
            ->firstOrFail();

        $template = $migratedDnsTemplate->dnsCustomerTemplate;

        if (! $template instanceof DnsCustomerTemplate) {
            $this->logger->warning(
                "Tried to link migrated DNS template but it wasn't found, has it been deleted?",
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $domainDeployment->subscription->id,
                    LoggingContextKeys::META => [
                        'reference_template_id' => $referenceTemplateId,
                    ],
                ],
            );

            return;
        }

        $domainDeployment->template()->associate($template);
        $domainDeployment->save();
    }
}
