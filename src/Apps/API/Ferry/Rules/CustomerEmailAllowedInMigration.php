<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Str;
use Waterfront\Infra\Validation\AbstractValidator;

class CustomerEmailAllowedInMigration extends AbstractValidator implements DataAwareRule
{
    private ?string $referenceName = null;

    private ?string $referenceCustomerId = null;

    /** @var array<mixed> */
    private array $data = [];

    private string $message;

    /**
     * @param array<mixed> $data
     */
    public function setData(array $data): CustomerEmailAllowedInMigration|static
    {
        $this->data = $data;

        if (array_key_exists('referenceName', $data) && is_string($data['referenceName'])) {
            $this->referenceName = $data['referenceName'];
        }

        if (array_key_exists('referenceCustomerId', $data) && is_string($data['referenceCustomerId'])) {
            $this->referenceCustomerId = $data['referenceCustomerId'];
        }

        return $this;
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        $dotPosition = Str::position($attribute, '.');

        // When having multiple customers in the data, we want to get the data for this customer only.
        if (is_int($dotPosition)) {
            $atrKey = (int) Str::substr($attribute, 0, $dotPosition);

            if (array_key_exists($atrKey, $this->data) && is_array($this->data[$atrKey])) {
                $this->setData($this->data[$atrKey]);
            }
        }

        if ($this->referenceName === null || $this->referenceCustomerId === null) {
            $this->message = 'referenceName and referenceCustomerId is required and needs to be a string';

            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return $this->message;
    }
}
