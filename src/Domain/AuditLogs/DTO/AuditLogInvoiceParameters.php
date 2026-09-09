<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogInvoiceParameters implements AuditLogTranslationParameters
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
        /** @var Invoice $invoice */
        $invoice = $this->audit->auditable;

        return [
            'entity' => $translator->translate('audit-log-summary.entity.invoice'),
            'description' => $invoice->description ?? '',
            'event' => $translator->translate('audit-log-summary.event.' . strtolower($this->audit->event)),
        ];
    }
}
