<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

/**
 * Just here to be able to reference this in the code. This makes it easier to find slug checks.
 */
enum ProductSlug: string
{
    case TRANSFER_SERVICE = 'transfer_service';
    case MICROSOFT_COPILOT = 'microsoft-copilot-for-microsoft-365';
    case MICROSOFT_COPILOT_PARENT = 'microsoft-copilot-for-microsoft-365-parent';
    case EXTENSION_NL = 'extension_nl';
    case EXTENSION_COM = 'extension_com';
}
