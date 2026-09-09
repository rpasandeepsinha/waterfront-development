<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Waterfront\Domain\Servers\Enums\ServerType;

readonly class ImportHostingServersDTO
{
    public function __construct(
        public ServerType $serverType,
        public string $csvContents,
    ) {
    }
}
