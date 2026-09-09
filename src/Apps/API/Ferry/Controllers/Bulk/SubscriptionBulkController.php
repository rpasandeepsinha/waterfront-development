<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\SubscriptionBulkCreateRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleSubscriptionBulkPayloadJob;

class SubscriptionBulkController
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function create(SubscriptionBulkCreateRequest $request): HttpResponse
    {
        $job = new HandleSubscriptionBulkPayloadJob($request->all());
        $this->dispatcher->dispatch($job);

        return new HttpResponse('Successfully created bulk subscription jobs', Response::HTTP_MULTI_STATUS);
    }
}
