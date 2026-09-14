<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Validation\Rule;
use Waterfront\Domain\Servers\Models\Server;

class UpdateHostingServerRequest extends AbstractHostingServerRequest
{
    /**
     * Blank credentials on the edit form mean "keep the stored one".
     */
    private const array CREDENTIAL_FIELDS = [
        'username',
        'password',
        'loginkey',
        'secret_key',
    ];

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'hostname' => [
                'required',
                'string',
                'max:255',
                Rule::unique('hosting_servers', 'hostname')->ignore($this->routeServer()->id)->whereNull('deleted_at'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function submittedServerAttributes(): array
    {
        $server = $this->routeServer();
        $incomingAttributes = parent::submittedServerAttributes();

        $attributes = array_replace($server->attributesToArray(), $incomingAttributes);

        foreach (self::CREDENTIAL_FIELDS as $field) {
            if (
                ! array_key_exists($field, $incomingAttributes)
                || $incomingAttributes[$field] === null
                || $incomingAttributes[$field] === ''
            ) {
                $attributes[$field] = $server->getAttribute($field);
            }
        }

        return $attributes;
    }

    private function routeServer(): Server
    {
        $server = $this->route('server');
        assert($server instanceof Server);

        return $server;
    }
}
