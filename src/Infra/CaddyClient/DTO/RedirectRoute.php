<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class RedirectRoute
{
    /**
     * @param list<RedirectRouteMatch>  $match
     * @param list<RedirectRouteHandle> $handle
     */
    public function __construct(
        #[SerializedName('@id')]
        public string $id,
        public array $match,
        public array $handle,
        public bool $terminal = true,
    ) {
    }
}
