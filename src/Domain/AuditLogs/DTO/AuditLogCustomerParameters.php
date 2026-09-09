<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogCustomerParameters implements AuditLogTranslationParameters
{
    public function __construct(
        private readonly string $entityName,
        private readonly Audit $audit,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(TranslatorInterface $translator): array
    {
        if ($this->entityName === 'customer') {
            $translationKey = 'audit-log-summary.entity.customer';
        } else {
            $translationKey = sprintf('audit-log-summary.entity.customer.%s', $this->entityName);
        }

        /** @var Customer $customer */
        $customer = $this->audit->auditable;

        return [
            'entity' => $translator->translate($translationKey),
            'name' => $customer->contact_name ?? '',
            'event' => $translator->translate('audit-log-summary.event.' . strtolower($this->audit->event)),
        ];
    }
}
