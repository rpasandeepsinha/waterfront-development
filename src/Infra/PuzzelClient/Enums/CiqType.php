<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Enums;

enum CiqType: string
{
    case CALL_REQUEST_FIRST = 'CallRequestFirst';
    case CALL_AGENT_FIRST = 'CallAgentFirst';
}
