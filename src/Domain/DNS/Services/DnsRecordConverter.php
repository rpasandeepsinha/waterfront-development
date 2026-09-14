<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Waterfront\Domain\DNS\Entities\DnsRecords\CaaRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\TlsaRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsTemplateNotConvertibleException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Support\Helpers\IdnHelper;

class DnsRecordConverter
{
    public function transformToTypedRecord(DnsCustomerTemplateRecord $record, string $zone): DnsRecordInterface
    {
        return match (DnsRecordType::tryFrom($record->type)) {
            DnsRecordType::CAA => $this->toCaaRecord($record, $zone),
            DnsRecordType::CNAME => $this->toCnameRecord($record, $zone),
            DnsRecordType::MX => $this->toMxRecord($record, $zone),
            DnsRecordType::SRV => $this->toSrvRecord($record, $zone),
            DnsRecordType::TLSA => $this->toTlsaRecord($record, $zone),
            default => $this->toDefaultRecord($record, $zone),
        };
    }

    private function toDefaultRecord(DnsCustomerTemplateRecord $record, string $zone): DefaultRecord
    {
        return new DefaultRecord(
            $record->type,
            $this->parseName($record, $zone),
            $this->normalizeContent($record, $zone),
            $record->ttl,
            $record->disabled,
        );
    }

    private function toCaaRecord(DnsCustomerTemplateRecord $record, string $zone): CaaRecord
    {
        return new CaaRecord(
            $this->parseName($record, $zone),
            $record->content,
            $record->ttl,
            $record->disabled,
        );
    }

    private function toCnameRecord(DnsCustomerTemplateRecord $record, string $zone): CnameRecord
    {
        return new CnameRecord(
            $this->parseName($record, $zone),
            $this->normalizeContent($record, $zone),
            $record->ttl,
            $record->disabled,
        );
    }

    private function toMxRecord(DnsCustomerTemplateRecord $record, string $zone): MxRecord
    {
        if ($record->priority === null) {
            throw new DnsTemplateNotConvertibleException('MX', $record->name, 'No priority set.');
        }

        return new MxRecord(
            $this->parseName($record, $zone),
            $this->normalizeContent($record, $zone),
            $record->priority,
            $record->ttl,
            $record->disabled,
        );
    }

    private function toSrvRecord(DnsCustomerTemplateRecord $record, string $zone): SrvRecord
    {
        if ($record->priority === null) {
            throw new DnsTemplateNotConvertibleException('SRV', $record->name, 'No priority set.');
        }

        if ($record->weight === null) {
            throw new DnsTemplateNotConvertibleException('SRV', $record->name, 'No weight set.');
        }

        if ($record->port === null) {
            throw new DnsTemplateNotConvertibleException('SRV', $record->name, 'No port set.');
        }

        return new SrvRecord(
            $this->parseName($record, $zone),
            $this->normalizeContent($record, $zone),
            $record->priority,
            $record->weight,
            $record->port,
            $record->ttl,
            $record->disabled,
        );
    }

    private function toTlsaRecord(DnsCustomerTemplateRecord $record, string $zone): TlsaRecord
    {
        return new TlsaRecord(
            $this->parseName($record, $zone),
            $record->content,
            $record->ttl,
            $record->disabled,
        );
    }

    private function parseName(DnsCustomerTemplateRecord $record, string $zone): string
    {
        return IdnHelper::toAscii(str_replace('@', $zone, $record->name));
    }

    private function parseContent(DnsCustomerTemplateRecord $record, string $zone): string
    {
        return str_replace('@', $zone, $record->content);
    }

    private function normalizeContent(DnsCustomerTemplateRecord $record, string $zone): string
    {
        return match (DnsRecordType::tryFrom($record->type)) {
            DnsRecordType::ALIAS,
            DnsRecordType::CNAME,
            DnsRecordType::MX,
            DnsRecordType::NS,
            DnsRecordType::SRV,
                => IdnHelper::toAscii($this->parseContent($record, $zone)),
            default => $record->content,
        };
    }
}
