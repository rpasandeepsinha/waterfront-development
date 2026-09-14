<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Container\Container;
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
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription_id,
            'name' => $this->resource->name,
            'record_type' => $this->resource->record_type->value,
            'change_type' => $this->resource->change_type->value,
            'agent_type' => $translator->translate(
                sprintf('dns.agent_type.%s', $this->resource->agent_type->value),
            ),
            'content' => $this->resource->content,
            'ttl' => $this->resource->ttl,
            'priority' => $this->resource->priority,
            'weight' => $this->resource->weight,
            'port' => $this->resource->port,
            'ip_address' => $this->resource->ip_address,
            'created_at' => $this->resource->created_at?->format(DateTimeFormat::DEFAULT),
            'updated_at' => $this->resource->updated_at?->format(DateTimeFormat::DEFAULT),
        ];
    }
}
