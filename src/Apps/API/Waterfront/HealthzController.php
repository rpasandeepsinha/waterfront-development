<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront;

use Illuminate\Http\Response;

class HealthzController
{
    /**
     * This endpoint is a health check for Intra to check if the software is still responding.
     * The name historically comes from Google’s internal practices. They're called "z-pages".
     */
    public function index(): Response
    {
        return new Response();
    }
}
