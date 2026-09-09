<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\HostingBulkMigrationRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleHostingBulkPayloadJob;

class HostingBulkMigrationController
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function execute(HostingBulkMigrationRequest $request): HttpResponse
    {
        $job = new HandleHostingBulkPayloadJob($request->all());
        $this->jobDispatcher->dispatch($job);

        return new HttpResponse('Successfully created bulk hosting migration jobs', Response::HTTP_MULTI_STATUS);
    }
}
