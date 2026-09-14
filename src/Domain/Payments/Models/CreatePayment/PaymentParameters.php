<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Models\CreatePayment;

class PaymentParameters
{
    /** @param mixed[] $metaData*/
    public function __construct(
        public readonly string $currency,
        public readonly string $amount,
        public readonly string $description,
        public readonly string $redirectUrl,
        public readonly string $webhookUrl,
        public readonly ?string $method = null,
        public readonly array $metaData = [],
    ) {
    }

    /** @return mixed[] */
    public function toArray(): array
    {
        $parameters = [
            'amount' => [
                'currency' => $this->currency,
                'value' => $this->amount,
            ],
            'description' => $this->description,
            'redirectUrl' => $this->redirectUrl,
            'webhookUrl' => $this->webhookUrl,
            'metadata' => $this->metaData,
        ];

        if ($this->method !== null) {
            $parameters['method'] = $this->method;
        }

        return $parameters;
    }
}
