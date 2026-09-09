<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Enums;

enum VirtualMachineState: string
{
    case START = 'start';
    case STOP = 'stop';
    case REBOOT = 'reboot';
    case REINSTALL = 'reinstall';
    case DESTROY = 'destroy';
}
