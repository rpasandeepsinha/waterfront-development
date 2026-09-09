<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Enums;

enum RequestStatus: string
{
    case Alerting = 'Alerting';
    case Allocated = 'Allocated';
    case Busy = 'Busy';
    case CallbackInQueue = 'CallbackInQueue';
    case Connected = 'Connected';
    case Connecting = 'Connecting';
    case Disconnected = 'Disconnected';
    case Failure = 'Failure';
    case InQueue = 'InQueue';
    case InService = 'InService';
    case NoAnswer = 'NoAnswer';
    case OnHold = 'OnHold';
    case Searching = 'Searching';
    case Setup = 'Setup';
}
