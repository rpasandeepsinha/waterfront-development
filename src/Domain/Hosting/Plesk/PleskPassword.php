<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk;

use Waterfront\Infra\PasswordGenerator\AbstractGenerator;
use Waterfront\Infra\PasswordGenerator\DTO\Rule;
use Waterfront\Infra\PasswordGenerator\Enums\CharacterSet;

class PleskPassword extends AbstractGenerator
{
    public const int MIN_LENGTH = 18;

    public function __construct()
    {
        $this->addRule(new Rule(CharacterSet::LOWERCASE));
        $this->addRule(new Rule(CharacterSet::UPPERCASE));
        $this->addRule(new Rule(CharacterSet::DIGIT));
        $this->addRule(new Rule(CharacterSet::PUNCT, exclusions: '\/ \'"'));
    }
}
