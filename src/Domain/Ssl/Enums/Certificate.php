<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Enums;

class Certificate
{
    public const string MAIN = 'main';
    public const string INTERMEDIATE = 'intermediate';
    public const string ROOT = 'root';
}
