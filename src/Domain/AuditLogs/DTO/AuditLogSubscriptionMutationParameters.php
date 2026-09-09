<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogSubscriptionMutationParameters implements AuditLogTranslationParameters
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
        /** @var SubscriptionMutation $mutation */
        $mutation = $this->audit->auditable;
        $subscription = $mutation->subscription;

        $event = $translator->translate('audit-log-summary.subscription-mutation.default');

        if ($mutation->billing_period !== $subscription->billing_period && $mutation->contract_period !== $subscription->contract_period) {
            $event = $translator->translate('audit-log-summary.subscription-mutation.contract-extension');
        }

        if ($mutation->product_id !== $subscription->product->id) {
            $event = $translator->translate('audit-log-summary.subscription-mutation.product-change');
        }

        return [
            'entity' => $translator->translate('audit-log-summary.entity.subscription-mutation'),
            'domain' => $subscription->domain ?? '',
            'event' => $event,
        ];
    }
}
