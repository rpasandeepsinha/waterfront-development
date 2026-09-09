<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Enums;

enum MediaType: string
{
    case Undefined = 'Undefined';
    case Phone = 'Phone';
    case EMail = 'EMail';
    case Sms = 'Sms';
    case Web = 'Web';
    case Chat = 'Chat';
    case Social = 'Social';
}
