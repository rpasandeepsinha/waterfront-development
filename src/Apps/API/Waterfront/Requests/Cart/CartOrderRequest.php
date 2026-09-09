<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Cart;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Cart\Validators\CartValidatorFactory;

class CartOrderRequest extends FormRequest
{
    /**
     * {@inheritDoc}
     */
    protected function getValidatorInstance(): Validator
    {
        /**
         * Override the annotation from parent class.
         *
         */
        if ($this->validator !== null) {
            return $this->validator;
        }

        $factory = $this->container->make(CartValidatorFactory::class);
        $validator = $this->createDefaultValidator($factory);

        if (method_exists($this, 'withValidator')) {
            $this->withValidator($validator);
        }

        $this->setValidator($validator);

        return $this->validator;
    }
}
