<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts\Enums;

enum Environments: string
{
    case YOURHOSTING_UAT = 'yourhosting-uat';
    case YOURHOSTING_PRODUCTION = 'yourhosting-prod';
    case VERSIO_UAT = 'versio-uat';
    case VERSIO_PRODUCTION = 'versio-prod';
}
