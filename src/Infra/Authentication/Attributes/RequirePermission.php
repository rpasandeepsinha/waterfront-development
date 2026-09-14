<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\Attributes;

use Attribute;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
readonly class RequirePermission
{
    public function __construct(
        public Permissions $permission,
        public ?SchemaId $schemaId = null,
    ) {
    }
}
