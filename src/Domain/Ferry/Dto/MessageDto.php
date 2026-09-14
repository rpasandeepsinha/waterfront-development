<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto;

abstract class MessageDto
{
    /** @var array<Parameter> */
    private array $baseParameters = [];

    /** @var array<Parameter> */
    private array $parameters = [];

    /**
     * @param array<Parameter|object> $parameters
     * @param array<Parameter|object> $baseParameters
     */
    protected function __construct(
        private readonly string $message,
        array $parameters,
        array $baseParameters,
    ) {
        foreach ($parameters as $parameter) {
            if (! $parameter instanceof Parameter) {
                continue;
            }

            $this->addParameter($parameter);
        }

        foreach ($baseParameters as $parameter) {
            if (! $parameter instanceof Parameter) {
                continue;
            }

            $this->addBaseParameter($parameter);
        }
    }

    final public function addBaseParameter(Parameter $parameter): self
    {
        $this->baseParameters[] = $parameter;

        return $this;
    }

    final public function addParameter(Parameter $parameter): self
    {
        $this->parameters[] = $parameter;

        return $this;
    }

    /**
     * @return array<string,array<string, int|string>|string>
     */
    final public function toArray(): array
    {
        $data = [];
        foreach ($this->parameters as $parameter) {
            $data[$parameter->getKey()] = $parameter->getValue();
        }

        $baseParameters = [];
        foreach ($this->baseParameters as $parameter) {
            $baseParameters[$parameter->getKey()] = $parameter->getValue();
        }

        return [
            'message' => $this->message,
            'baseParameters' => $baseParameters,
            'parameters' => $data,
        ];
    }
}
