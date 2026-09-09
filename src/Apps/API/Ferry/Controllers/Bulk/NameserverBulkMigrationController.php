<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\NameserverBulkCreateRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleNameserverBulkPayloadJob;

class NameserverBulkMigrationController
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function execute(NameserverBulkCreateRequest $request): HttpResponse
    {
        $job = new HandleNameserverBulkPayloadJob($request->all());
        $this->jobDispatcher->dispatch($job);

        return new HttpResponse('Successfully created bulk nameserver migration jobs', Response::HTTP_MULTI_STATUS);
    }
}
