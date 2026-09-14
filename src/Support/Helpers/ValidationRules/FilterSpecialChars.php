<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers\ValidationRules;

use Illuminate\Container\Container;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class FilterSpecialChars extends AbstractValidator
{
    public function __construct(
        private readonly string $excludechars = '',
        private readonly string $characters = '`~!@#$%^&*()_+={}|[]\:;<,>.?',
        private string $foundCharacters = '',
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (is_integer($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return false;
        }

        $chars = array_diff(mb_str_split($this->characters), mb_str_split($this->excludechars));
        $passes = true;
        foreach ($chars as $char) {
            if (str_contains($value, $char)) {
                $passes = false;
                $this->foundCharacters .= $char;
            }
        }

        return $passes;
    }

    protected function message(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return $translator->translate('customer.specialchar-error', ['characters' => $this->foundCharacters]);
    }
}
