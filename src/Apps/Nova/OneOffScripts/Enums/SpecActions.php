<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts\Enums;

enum SpecActions: string
{
    case CREATE = 'create';
    case DELETE = 'delete';
}
