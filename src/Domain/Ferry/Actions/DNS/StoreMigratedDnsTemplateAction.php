<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\DNS;

use Waterfront\Domain\Customers\DTO\DnsTemplateDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplateRecord;

class StoreMigratedDnsTemplateAction
{
    public function execute(
        DnsTemplateDTO $dnsTemplateDTO,
        Customer $customer,
        MigratedCustomer $migratedCustomer
    ): void {
        $alreadyMigrated = MigratedDnsTemplate::query()
            ->where('reference_template_id', $dnsTemplateDTO->referenceTemplateId)
            ->where('migrated_customer_id', $migratedCustomer->id)
            ->exists();

        if ($alreadyMigrated) {
            return;
        }

        $template = new DnsCustomerTemplate();
        $template->name = $dnsTemplateDTO->name;
        $template->customer()->associate($customer);
        $template->save();

        $migratedTemplate = new MigratedDnsTemplate();
        $migratedTemplate->reference_template_id = $dnsTemplateDTO->referenceTemplateId;
        $migratedTemplate->dnsCustomerTemplate()->associate($template);
        $migratedTemplate->migratedCustomer()->associate($migratedCustomer);
        $migratedTemplate->save();

        foreach ($dnsTemplateDTO->records as $recordDTO) {
            $record = new DnsCustomerTemplateRecord();
            $record->name = $recordDTO->name;
            $record->content = $recordDTO->content;
            $record->type = $recordDTO->type;
            $record->ttl = $recordDTO->ttl;
            $record->priority = $recordDTO->priority;
            $record->port = $recordDTO->port;
            $record->weight = $recordDTO->weight;
            $record->disabled = $recordDTO->disabled;
            $record->template()->associate($template);
            $record->save();

            $migratedRecord = new MigratedDnsTemplateRecord();
            $migratedRecord->reference_record_id = $recordDTO->referenceRecordId;
            $migratedRecord->migratedDnsTemplate()->associate($migratedTemplate);
            $migratedRecord->dnsCustomerTemplateRecord()->associate($record);
            $migratedRecord->save();
        }
    }
}
