<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogDeploymentParameters implements AuditLogTranslationParameters
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
        /** @var DomainDeployment|SslDeployment|ResellerHostingDeployment|HostingDeployment $deployment */
        $deployment = $this->audit->auditable;
        $productGroupSlug = $deployment->subscription->product->productGroup->slug->value;

        return [
            'entity' => $translator->translate("audit-log-summary.entity.subscription.$productGroupSlug"),
            'domain' => $deployment->subscription->domain ?? '',
            'event' => $translator->translate('audit-log-summary.event.' . strtolower($this->audit->event)),
        ];
    }
}
