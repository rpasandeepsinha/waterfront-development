<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\DTO;

use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

class Redirect
{
    public function __construct(
        public string $source,
        public string $destination,
        public RedirectType $redirectType,
    ) {
    }
}
