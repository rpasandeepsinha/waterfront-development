<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogSubscriptionParameters implements AuditLogTranslationParameters
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
        /** @var Subscription $subscription */
        $subscription = $this->audit->auditable;
        $productGroupSlug = $subscription->product->productGroup->slug->value;

        return [
            'entity' => $translator->translate("audit-log-summary.entity.subscription.$productGroupSlug"),
            'domain' => $subscription->domain ?? '',
            'event' => $translator->translate('audit-log-summary.event.' . strtolower($this->audit->event)),
        ];
    }
}
