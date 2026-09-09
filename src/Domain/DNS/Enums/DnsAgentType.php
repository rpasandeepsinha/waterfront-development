<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Enums;

enum DnsAgentType: string
{
    case CUSTOMER = 'customer';
    case CS_AGENT = 'customer_support_agent';
    case SYSTEM = 'system_process';
}
