<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\ValidationBulkRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleValidationBulkPayloadJob;

class ValidationBulkController
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function create(ValidationBulkRequest $request): HttpResponse
    {
        $job = new HandleValidationBulkPayloadJob($request->all());
        $this->dispatcher->dispatch($job);

        return new HttpResponse('Successfully created bulk validation jobs', Response::HTTP_MULTI_STATUS);
    }
}
