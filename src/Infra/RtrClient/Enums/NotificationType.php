<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum NotificationType: string
{
    case SSLCertificateNotification = 'SSLCertificateNotification';
    case CreateDomainNotification = 'CreateDomainNotification';
    case DeleteDomainNotification = 'DeleteDomainNotification';
    case RenewDomainNotification = 'RenewDomainNotification';
    case UpdateDomainNotification = 'UpdateDomainNotification';
    case TransferDomainNotification = 'TransferDomainNotification';
    case DomainNotification = 'DomainNotification';
    case NOTIFICATION = 'Notification';
    case SSLCertificateExpiryReportNotification = 'SSLCertificateExpiryReportNotification';
}
