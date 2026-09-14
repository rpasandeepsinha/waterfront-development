<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\AuditLogs;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonException;
use stdClass;
use Waterfront\Domain\AuditLogs\Actions\GetAuditableNameAction;
use Waterfront\Domain\AuditLogs\Actions\GetSubjectTypeAction;
use Waterfront\Domain\AuditLogs\Actions\SummarizeAuditLogAction;
use Waterfront\Domain\AuditLogs\DTO\AuditLoggableIdentity;
use Waterfront\Domain\History\Models\Audit;

/**
 * @property Audit $resource
 */
class AuditLogResource extends JsonResource
{
    public const string SYSTEM = 'system';

    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        $getAuditableNameAction = new GetAuditableNameAction();
        $getSubjectTypeAction = new GetSubjectTypeAction();
        /** @var SummarizeAuditLogAction $summarizeAuditLog */
        $summarizeAuditLog = Container::getInstance()->make(SummarizeAuditLogAction::class);

        $identity = null;
        if ($this->resource->identity_uuid !== null) {
            try {
                $identityMetadata = json_decode(
                    $this->resource->identity_metadata ?? '',
                    false,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                $identityMetadata = null;
            }

            assert($identityMetadata instanceof stdClass);

            $identity = new AuditLoggableIdentity(
                $this->resource->identity_uuid,
                $identityMetadata->email ?? null,
                $identityMetadata->schemaId ?? null,
            );
        }

        return [
            'id' => $this->resource->id,
            'time' => $this->resource->created_at,
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'actor' => [
                'name' => $identity->email ?? null,
                'type' => $identity?->getIdentityType() ?? self::SYSTEM,
            ],
            'subject' => [
                'type' => $getSubjectTypeAction->execute($this->resource->auditable_type),
                'id' => $this->resource->auditable_id,
                'name' => $getAuditableNameAction->execute($this->resource),
            ],
            'action' => [
                'summary' => $summarizeAuditLog->execute($this->resource, $identity),
                'type' => $this->resource->event,
                'old_values' => $this->resource->old_values,
                'new_values' => $this->resource->new_values,
            ],
        ];
    }
}
