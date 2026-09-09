<?php

declare(strict_types=1);

namespace Waterfront\Domain\History\Models;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use OwenIt\Auditing\Models\Audit as AuditModel;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int              $id
 * @property ?string          $user_type
 * @property ?int             $user_id
 * @property string           $event
 * @property string           $auditable_type
 * @property int              $auditable_id
 * @property array            $old_values
 * @property array            $new_values
 * @property ?string          $url
 * @property ?string          $ip_address
 * @property ?string          $user_agent
 * @property ?array           $tags
 * @property ?CarbonImmutable $created_at
 * @property ?Model           $auditable
 * @property ?UuidInterface   $identity_uuid
 * @property ?string          $identity_metadata
 *
 * @mixin Builder<Audit>
 */
class Audit extends AuditModel
{
    protected $appends = [
        'model',
        'model_event',
    ];

    /**
     * Get the auditable model to which this Audit belongs.
     * Removing the softDeleting scope on the MorphTo because some related models contain a softDelete
     * which will break during the eager loading of the morphTo.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(SoftDeletingScope::class);
    }

    // "$casts" array doesn't work because of array to string conversion error
    public function getAuditableIdAttribute(): int
    {
        return (int) $this->attributes['auditable_id'];
    }

    public function getModelEventAttribute(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);
        return $translator->translate('audit.events.' . $this->event);
    }

    public function getUserIdAttribute(): int
    {
        return (int) $this->attributes['user_id'];
    }

    public function getModelAttribute(): string
    {
        $type = $this->auditable_type;
        $class = basename(str_replace('\\', '/', $type));

        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);
        $type = $translator->translate('audit.types.' . $class);

        $object = $this->auditable;
        if ($object !== null && property_exists($object, 'name') && $object->name !== null && $object->name !== '') {
            return $type . ' "' . $object->name . '"';
        }

        /** @var mixed[]|string $oldValues */
        $oldValues = $this->old_values;
        if (is_array($oldValues) && array_key_exists('name', $oldValues) && $oldValues['name'] !== null && $oldValues['name'] !== '') {
            return $type . ' "' . $oldValues['name'] . '"';
        }

        /** @var mixed[]|string $newValues */
        $newValues = $this->old_values;
        if (is_array($newValues) && array_key_exists('name', $newValues) && $newValues['name'] !== null && $newValues['name'] !== '') {
            return $type . ' "' . $newValues['name'] . '"';
        }

        return $type;
    }

    protected function casts(): array
    {
        return [
            'identity_uuid' => UuidCast::class,
            'old_values'   => 'json',
            'new_values'   => 'json',
        ];
    }
}
