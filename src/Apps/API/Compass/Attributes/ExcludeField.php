<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ExcludeField
{
    /** @var string[] */
    public readonly array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }
}
