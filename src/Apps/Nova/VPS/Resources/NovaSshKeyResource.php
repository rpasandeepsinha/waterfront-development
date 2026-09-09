<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\VPS\Models\SshKey;

/**
 * @property SshKey $resource
 */
class NovaSshKeyResource extends Resource
{
    public static string $model = SshKey::class;

    public static function getTranslationKey(): string
    {
        return 'ssh-key';
    }

    public function title(): string
    {
        return $this->resource->fingerprint;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.ssh_keys');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('customer.singular'),
                'customer',
                NovaCustomerResource::class
            ),
            Text::make(
                self::translate('ssh_key.attributes.uuid'),
                'uuid'
            )->onlyOnDetail(),
            Text::make(
                self::translate('ssh_key.attributes.key_name'),
                'key_name'
            ),
            Text::make(
                self::translate('ssh_key.attributes.public_key'),
                'public_key'
            )->onlyOnDetail(),
            Text::make(
                self::translate('ssh_key.attributes.fingerprint'),
                'fingerprint'
            ),
            Text::make(
                self::translate('ssh_key.attributes.cloudstack_ssh_name'),
                'cloudstack_ssh_name'
            )->onlyOnDetail(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }
}
