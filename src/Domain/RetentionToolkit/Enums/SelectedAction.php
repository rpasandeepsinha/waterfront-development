<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Enums;

enum SelectedAction: string
{
    case DM_OPTION_1 = 'dm_option_1';
    case DG_OPTION_1A = 'dg_option_1a';
    case DG_OPTION_1D = 'dg_option_1d';
    case TK_OPTION_1 = 'tk_option_1';
    case TK_OPTION_2 = 'tk_option_2';
    case TK_OPTION_3 = 'tk_option_3';
    case TK_OPTION_5 = 'tk_option_5';
    case TK_OPTION_6 = 'tk_option_6';
    case RF = 'rf';
    case BZ = 'bz';

    public function isDowngrade(): bool
    {
        return in_array($this, [self::DG_OPTION_1A, self::DG_OPTION_1D], true);
    }
}
