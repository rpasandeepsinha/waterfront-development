<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum EventType: string
{
    case RequestCertificateEvent = 'RequestCertificateEvent';
    case CreateDomainEvent = 'CreateDomainEvent';
    case DeleteDomainEvent = 'DeleteDomainEvent';
    case RenewDomainEvent = 'RenewDomainEvent';
    case UpdateDomainEvent = 'UpdateDomainEvent';
    case TransferDomainEvent = 'TransferDomainEvent';
    case VALIDATE_CONTACT_EVENT = 'ValidateContactEvent';
    case SSLCertificateExpiryReportEvent = 'SSLCertificateExpiryReportEvent';
}
