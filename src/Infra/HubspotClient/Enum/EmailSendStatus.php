<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\Enum;

/**
 * @see https://developers.hubspot.com/beta-docs/guides/api/marketing/emails/single-send-api#query-the-status-of-an-email-send
 */
enum EmailSendStatus: string
{
    case CANCELED = 'CANCELED';
    case COMPLETE = 'COMPLETE';
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
}
