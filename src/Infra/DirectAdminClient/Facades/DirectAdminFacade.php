<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Facades;

use Illuminate\Support\Facades\Facade;

class DirectAdminFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'directadmin';
    }
}
