<?php

declare(strict_types=1);

namespace Waterfront\Infra\PasswordGenerator\DTO;

use Waterfront\Infra\PasswordGenerator\Enums\CharacterSet;

class Rule
{
    public function __construct(
        public readonly CharacterSet $characterSet,
        public readonly int $minOccurrence = 1,
        public readonly ?int $maxOccurrence = null,
        public readonly ?string $exclusions = null,
    ) {
    }
}
