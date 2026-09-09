<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Hydrators;

use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\NsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

class DnsRecordToPowerDnsDehydrator
{
    /**
     * @return array<string, string|int|bool>
     */
    public function dehydrate(DnsRecordInterface $record): array
    {
        if ($record instanceof CnameRecord) {
            return $this->dehydrateCnameRecord($record);
        }
        if ($record instanceof MxRecord) {
            return $this->dehydrateMxRecord($record);
        }
        if ($record instanceof SrvRecord) {
            return $this->dehydrateSrvRecord($record);
        }
        if ($record instanceof NsRecord) {
            return $this->dehydrateNsRecord($record);
        }

        assert($record instanceof DefaultRecord);

        return $this->dehydrateDefaultRecord($record);
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function dehydrateCnameRecord(CnameRecord $record): array
    {
        return [
            'type'     => $record->getType(),
            'name'     => $record->getNamePlusDot(),
            'content'  => $record->getContentPlusDot(),
            'ttl'      => $record->getTtl(),
            'disabled' => $record->isDisabled(),
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function dehydrateMxRecord(MxRecord $record): array
    {
        return [
            'type'     => $record->getType(),
            'name'     => $record->getNamePlusDot(),
            'content'  => $record->getContentPlusDot(),
            'priority' => $record->getPriority(),
            'ttl'      => $record->getTtl(),
            'disabled' => $record->isDisabled(),
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function dehydrateSrvRecord(SrvRecord $record): array
    {
        return [
            'type'     => $record->getType(),
            'name'     => $record->getNamePlusDot(),
            'content'  => $record->getContentPlusDot(),
            'priority' => $record->getPriority(),
            'weight'   => $record->getWeight(),
            'port'     => $record->getPort(),
            'ttl'      => $record->getTtl(),
            'disabled' => $record->isDisabled(),
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function dehydrateNsRecord(NsRecord $record): array
    {
        return [
            'type'     => $record->getType(),
            'name'     => $record->getName(),
            'content'  => $record->getContentPlusDot(),
            'ttl'      => $record->getTtl(),
            'disabled' => $record->isDisabled(),
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function dehydrateDefaultRecord(DefaultRecord $record): array
    {
        return [
            'type'     => $record->getType(),
            'name'     => $record->getNamePlusDot(),
            'content'  => $record->getContent(),
            'ttl'      => $record->getTtl(),
            'disabled' => $record->isDisabled(),
        ];
    }
}
