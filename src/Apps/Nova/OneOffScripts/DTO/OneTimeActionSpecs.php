<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts\DTO;

use Waterfront\Apps\Nova\OneOffScripts\Enums\Environments;
use Waterfront\Apps\Nova\OneOffScripts\Enums\SpecActions;

readonly class OneTimeActionSpecs
{
    /**
     * @param array<Environments> $environments
     * @param array<string>       $productSlugs
     */
    public function __construct(
        public SpecActions $action,
        public array $environments,
        public string $specName,
        public string $specValue,
        public array $productSlugs,
    ) {
    }
}
