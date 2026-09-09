<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Services;

use SandwaveIo\BaseKit\BaseKit;
use Waterfront\Domain\Servers\Models\Server;

interface BasekitFactoryInterface
{
    public function make(Server $server): BaseKit;
}
