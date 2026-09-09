<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Dns;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property DnsRecordChange $resource
 */
class DnsRecordChangeResource extends Resource
{
    /**
     *
     * @throws BindingResolutionException
     *
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription_id,
            'name' => $this->resource->name,
            'record_type' => $this->resource->record_type->value,
            'change_type' => $this->resource->change_type->value,
            'agent_type' => $translator->translate(
                sprintf('dns.agent_type.%s', $this->resource->agent_type->value)
            ),
            'content' => $this->resource->content,
            'ttl' => $this->resource->ttl,
            'priority' => $this->resource->priority,
            'weight' => $this->resource->weight,
            'port' => $this->resource->port,
            'ip_address' => $this->resource->ip_address,
            'created_at' => $this->resource->created_at?->format(DateTimeFormat::DEFAULT),
            'updated_at' => $this->resource->updated_at?->format(DateTimeFormat::DEFAULT),
            'changed_by_metadata' => $this->resource->changed_by_metadata,
            'changed_by_uuid' => $this->resource->changed_by_uuid,
            'event' => $this->generateEvent($this->resource, $translator),
        ];
    }

    private function generateEvent(DnsRecordChange $dnsRecordChange, TranslatorInterface $translator): string
    {
        $changedBy = [];
        $email = '';

        if ($dnsRecordChange->changed_by_uuid !== null) {
            $changedBy = ['changed_by_uuid' => $dnsRecordChange->changed_by_uuid];
        }

        if ($dnsRecordChange->changed_by_metadata !== null) {
            $changedBy = [...$changedBy, 'changed_by_metadata' => json_decode($dnsRecordChange->changed_by_metadata, true)];
            if (
                is_array($changedBy['changed_by_metadata']) &&
                array_key_exists('email', $changedBy['changed_by_metadata']) &&
                $changedBy['changed_by_metadata']['email'] !== null) {
                $email = '(' . $changedBy['changed_by_metadata']['email'] . ')';
            }
        }

        return $translator->translate(
            'dns.record.changed',
            [
                'agent' => $translator->translate('dns.agent_type.' . $this->resource->agent_type->value),
                'email' => $email,
                'change-type' => $translator->translate('dns.change_type.' . $this->resource->change_type->value),
                'record-type' => $this->resource->record_type->value,
            ]
        );
    }
}
