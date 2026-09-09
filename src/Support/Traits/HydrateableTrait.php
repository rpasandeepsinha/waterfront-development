<?php

declare(strict_types=1);

namespace Waterfront\Support\Traits;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Support\Exceptions\HydratorValidationException;

trait HydrateableTrait
{
    public static function getRequiredFields(): array
    {
        return [];
    }

    public static function create(array $data): static
    {
        // Only filter out null values
        $data = array_filter(
            $data,
            fn ($property): bool => ! is_null($property)
        );

        self::validateRequiredFields($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $hydrator = new Hydrator();

        return $hydrator->extract($this);
    }

    /**
     * @param mixed[] $data
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::getRequiredFields() as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new HydratorValidationException(
                    self::class . '::validateRequiredFields - '
                    . 'Required field ' . $fieldName . ' is missing from the data.'
                );
            }
        }
    }
}
