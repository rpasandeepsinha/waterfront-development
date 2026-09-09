<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Store;

class StartSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $sessionHandler = new NoopSessionHandler();
        $session = new Store('', $sessionHandler);
        $request->setLaravelSession($session);

        return $next($request);
    }
}
