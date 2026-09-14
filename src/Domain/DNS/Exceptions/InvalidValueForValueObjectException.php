<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Exceptions;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidValueForValueObjectException extends HttpException
{
    /**
     * @param class-string $valueObject
     *
     * @throws ReflectionException
     */
    public function __construct(string $value, string $valueObject)
    {
        $refl = new ReflectionClass($valueObject);
        $name = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $refl->getShortName()));
        parent::__construct(
            422,
            '"' . $value . '" is not a valid value for value object ' . $name,
        );
    }
}
