<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogCloudstackChildSubscriptionsParameters implements AuditLogTranslationParameters
{
    public function __construct(
        private readonly Audit $audit,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(TranslatorInterface $translator): array
    {
        /** @var VirtualMachineDeployment|VolumeDeployment $deployment */
        $deployment = $this->audit->auditable;

        $productGroupSlug = $deployment->subscription->product->productGroup->slug->value;

        return [
            'entity' => $translator->translate("audit-log-summary.entity.subscription.$productGroupSlug"),
            'domain' => $deployment->managerDomainDeployment->domain_name ?? '',
            'event' => $translator->translate('audit-log-summary.event.' . strtolower($this->audit->event)),
        ];
    }
}
