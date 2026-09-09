<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Enum;

/**
 * @see https://developers.hubspot.com/beta-docs/guides/api/marketing/emails/single-send-api#sendresul
 */
enum EmailSendResult: string
{
    case BLOCKED_DOMAIN = 'BLOCKED_DOMAIN';
    case INVALID_FROM_ADDRESS = 'INVALID_FROM_ADDRESS';
    case MISSING_CONTENT = 'MISSING_CONTENT';
    case MISSING_TEMPLATE_PROPERTIES = 'MISSING_TEMPLATE_PROPERTIES';
    case PORTAL_SUSPENDED = 'PORTAL_SUSPENDED';
    case PREVIOUSLY_BOUNCED = 'PREVIOUSLY_BOUNCED';
    case PREVIOUS_SPAM = 'PREVIOUS_SPAM';
    case QUEUED = 'QUEUED';
    case SENT = 'SENT';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case IDEMPOTENT_IGNORE = 'IDEMPOTENT_IGNORE';
}
