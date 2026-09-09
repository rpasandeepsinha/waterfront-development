<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * @inheritdoc
     */
    protected $except = [];
}
