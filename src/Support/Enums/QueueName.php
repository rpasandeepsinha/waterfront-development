<?php

declare(strict_types=1);

namespace Waterfront\Support\Enums;

enum QueueName: string
{
    case CLOUDSTACK = 'cloudstack';
    case CRM = 'crm';
    case CUSTOMERS = 'customers';
    case DNS = 'dns';
    case DEFAULT = 'default';
    case FERRY = 'ferry';
    case FERRY_BULK = 'ferry-bulk';
    case FERRY_PROXY = 'ferry-proxy';
    case FERRY_VALIDATION = 'ferry-validation';
    case FERRY_WEBHOOK = 'ferry-webhook';
    case HOSTING = 'hosting';
    case INVOICES = 'invoices';
    case MICROSOFT365 = 'microsoft365';
    case MIGRATIONS = 'migrations';
    case PARTNER_DOMAIN = 'partner-domain';
    case PARTNER_SSL = 'partner-ssl';
    case SUBSCRIPTIONS = 'subscriptions';
    case ONE_TIME_SCRIPTS = 'one-time-scripts';

    /**
     * This queue is being used for slow DirectAdmin API calls. Its
     * separated from the main hosting queue to prevent blocking
     * other hosting jobs when DirectAdmin API is unresponsive.
     */
    case TERMINATE_HOSTING = 'terminate-hosting';
}
