<?php

declare(strict_types=1);

namespace Waterfront\Infra\PasswordGenerator;

use Waterfront\Infra\PasswordGenerator\DTO\Rule;
use Waterfront\Infra\PasswordGenerator\Enums\CharacterSet;

class DefaultGenerator extends AbstractGenerator
{
    public function __construct()
    {
        $this->addRule(new Rule(CharacterSet::LOWERCASE));
        $this->addRule(new Rule(CharacterSet::UPPERCASE));
        $this->addRule(new Rule(CharacterSet::DIGIT));
        $this->addRule(new Rule(CharacterSet::PUNCT));
    }
}
