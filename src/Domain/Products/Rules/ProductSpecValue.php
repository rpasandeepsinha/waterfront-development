<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Rules;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Waterfront\Infra\Validation\AbstractValidator;

class ProductSpecValue extends AbstractValidator
{
    /** @var string */
    private $type;

    public function __construct(Request $request)
    {
        if (! is_string($request->name)) {
            return;
        }

        $config = Config::get('product-specs.' . $request->name);
        assert(is_array($config));

        $this->type = $config['type'];
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if ('boolean' === $this->type) {
            return $this->isBooleanValue($value);
        }

        if (in_array($this->type, ['integer', 'bytes'], true)) {
            return $this->isValidValue($value);
        }

        if ('string' === $this->type) {
            return true;
        }

        return false;
    }

    protected function message(): string
    {
        if ('boolean' === $this->type) {
            return 'The value must be either 1 or 0.';
        }

        if ('integer' === $this->type) {
            return 'The value must be an integer.';
        }

        return 'Unspecified validation error';
    }

    private function isBooleanValue(mixed $value): bool
    {
        return in_array($value, ['true', 'false', '1', '0', 'yes', 'no'], true);
    }

    private function isValidValue(mixed $value): bool
    {
        return is_numeric($value);
    }
}
