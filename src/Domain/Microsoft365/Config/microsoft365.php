<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'api_url' => Env::get('MICROSOFT365_API_URL'),
    'api_user' => Env::get('MICROSOFT365_API_USER'),
    'api_password' => Env::get('MICROSOFT365_API_PASSWORD'),
    'tenant_prefix' => Env::get('MICROSOFT365_TENANT_PREFIX'),
    'default_company_phone_number' => Env::get('MICROSOFT365_DEFAULT_COMPANY_NUMBER'),
    'customer_placeholder_id' => Env::get('MICROSOFT365_CUSTOMER_PLACEHOLDER_ID'),
    'webhook_username' => Env::get('MICROSOFT365_WEBHOOK_USERNAME'),
    'webhook_password' => Env::get('MICROSOFT365_WEBHOOK_PASSWORD'),
];
