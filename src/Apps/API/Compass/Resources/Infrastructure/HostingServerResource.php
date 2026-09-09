<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Policies\ServerPolicy;
use Waterfront\Domain\Servers\Models\Server;

/** @property Server $resource */
class HostingServerResource extends JsonResource
{
    /**
     * The credentials (password, loginkey, secret_key) are deliberately absent.
     *
     * @return array<string, array<int, string>|int|string|bool|null>
     */
    public function toArray($request): array
    {
        $serverPolicy = Container::getInstance()->make(ServerPolicy::class);

        return [
            'id' => $this->resource->id,
            'hostname' => $this->resource->hostname,
            'ipv4' => $this->resource->ipv4,
            'ipv6' => $this->resource->ipv6,
            'php_version' => $this->resource->php_version,
            'owner' => $this->resource->owner,
            'type' => $this->resource->type->value,
            'allow_new_websites' => $this->resource->allow_new_websites,
            'current_amount_websites' => $this->resource->number_of_websites,
            'max_websites' => $this->resource->maximum_websites,
            'domain' => $this->resource->domain,
            'name' => $this->resource->name,
            'port' => $this->resource->port,
            'use_ssl' => $this->resource->use_ssl,
            'username' => $this->resource->username,
            'available_actions' => $serverPolicy->getAvailableCompassActions($this->resource),
        ];
    }
}
