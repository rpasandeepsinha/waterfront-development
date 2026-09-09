<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Enums;

/**
 * This Enum represents the state a virtual machine can have in Cloudstack.
 *
 * @see https://cloudstack.apache.org/api/apidocs-4.20/apis/listVirtualMachines.html
 * @see https://cwiki.apache.org/confluence/display/CLOUDSTACK/CloudStack+objects+states
 */
enum CloudstackMachineState: string
{
    case RUNNING = 'Running';
    case STOPPED = 'Stopped';
    case DESTROYED = 'Destroyed';
    case PRESENT = 'Present';
    case EXPUNGED = 'Expunged';
    case UNKNOWN = 'Unknown';
    case STARTING = 'Starting';
    case ERROR = 'Error';
    case MIGRATING = 'Migrating';
    case STOPPING = 'Stopping';
    case SHUTDOWNED = 'Shutdowned';
}
