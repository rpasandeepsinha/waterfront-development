<?php

declare(strict_types=1);

namespace Waterfront\Infra\PasswordGenerator\Enums;

enum CharacterSet
{
    case LOWERCASE;
    case UPPERCASE;
    case DIGIT;
    case PUNCT;

    /**
     * @return array<int,int|string>
     */
    public function chars(): array
    {
        return match ($this) {
            self::LOWERCASE => range('a', 'z'),
            self::UPPERCASE => range('A', 'Z'),
            self::DIGIT => range(0, 9),
            self::PUNCT => str_split('!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~'),
        };
    }
}
