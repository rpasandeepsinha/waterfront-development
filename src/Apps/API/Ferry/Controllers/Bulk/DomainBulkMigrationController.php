<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\DomainBulkMigrationRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleTechnicalDomainBulkPayloadJob;

class DomainBulkMigrationController
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function execute(DomainBulkMigrationRequest $request): HttpResponse
    {
        $domainPayloads = $request->all();

        $this->dispatcher->dispatch(new HandleTechnicalDomainBulkPayloadJob($domainPayloads));

        return new HttpResponse(
            'Successfully created bulk technical domain migration jobs',
            Response::HTTP_MULTI_STATUS,
        );
    }
}
