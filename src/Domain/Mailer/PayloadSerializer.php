<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Illuminate\Contracts\Encryption\Encrypter;
use ReflectionClass;
use ReflectionParameter;
use SensitiveParameter;

class PayloadSerializer
{
    public function __construct(
        private readonly Encrypter $encrypter,
    ) {
    }

    public function serialize(MailTemplateInterface $template): ?string
    {
        $payload = get_object_vars($template);

        foreach ($this->getSensitiveParameters($template) as $parameter) {
            $payload[$parameter] = 'encrypted:' . $this->encrypter->encrypt($payload[$parameter]);
        }

        return $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string[]
     */
    private function getSensitiveParameters(MailTemplateInterface $template): array
    {
        $reflectionClass = new ReflectionClass($template);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return array_filter(
            array_map(
                fn (ReflectionParameter $parameter) => $parameter->getAttributes(SensitiveParameter::class) !== []
                    ? $parameter->getName()
                    : null,
                $constructor->getParameters(),
            ),
            fn (?string $parameterName): bool => $parameterName !== null,
        );
    }
}
