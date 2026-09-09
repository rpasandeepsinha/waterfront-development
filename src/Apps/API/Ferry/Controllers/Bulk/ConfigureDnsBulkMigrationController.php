<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers\Bulk;

use Illuminate\Bus\Dispatcher;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\Bulk\ConfigureDnsBulkRequest;
use Waterfront\Domain\Ferry\Jobs\Proxies\HandleConfigureDnsBulkPayloadJob;

class ConfigureDnsBulkMigrationController
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function execute(ConfigureDnsBulkRequest $request): HttpResponse
    {
        $dnsPayloads = $request->all();

        $this->dispatcher->dispatch(new HandleConfigureDnsBulkPayloadJob($dnsPayloads));

        return new HttpResponse('Successfully created bulk dns configure migration jobs', Response::HTTP_MULTI_STATUS);
    }
}
