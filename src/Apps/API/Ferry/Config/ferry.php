<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'url_ferry_api' => Env::get('URL_FERRY_API'),
    'ferry_azure_data_factory_job_api_key' => Env::get('FERRY_AZURE_DATA_FACTORY_JOB_API_KEY'),
    'ferry_azure_data_factory_job_api_url' => Env::get('FERRY_AZURE_DATA_FACTORY_JOB_API_URL'),
];
