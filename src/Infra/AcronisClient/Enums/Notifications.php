<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums;

enum Notifications: string
{
    case MAINTENANCE = 'maintenance';
    case QUOTA = 'quota';
    case REPORTS = 'reports';
    case BACKUP_ERROR = 'backup_error';
    case BACKUP_WARNING = 'backup_warning';
    case BACKUP_INFO = 'backup_info';
    case BACKUP_DAILY_REPORT = 'backup_daily_report';
    case BACKUP_CRITICAL = 'backup_critical';
    case DEVICE_CONTROL_WARNING = 'device_control_warning';
    case CERTIFICATE_MANAGEMENT_ERROR = 'certificate_management_error';
    case CERTIFICATE_MANAGEMENT_WARNING = 'certificate_management_warning';
    case CERTIFICATE_MANAGEMENT_INFO = 'certificate_management_info';
}
