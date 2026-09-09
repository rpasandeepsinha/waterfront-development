<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto;

class SuccessDto extends MessageDto
{
    /**
     * @param array<Parameter> $parameters
     * @param array<Parameter> $baseParameters
     */
    public static function create(string $message, array $parameters, array $baseParameters = []): SuccessDto
    {
        return new self($message, $parameters, $baseParameters);
    }
}
