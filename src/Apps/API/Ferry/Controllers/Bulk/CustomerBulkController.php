<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\CustomerBulkCreateRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleCustomerBulkPayloadJob;

class CustomerBulkController
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function create(CustomerBulkCreateRequest $request): HttpResponse
    {
        $job = new HandleCustomerBulkPayloadJob($request->all());
        $this->jobDispatcher->dispatch($job);

        return new HttpResponse('Successfully created bulk customer jobs', Response::HTTP_MULTI_STATUS);
    }
}
