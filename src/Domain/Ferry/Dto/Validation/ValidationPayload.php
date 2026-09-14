<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Validation;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Arr;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;

class ValidationPayload
{
    private ?string $jobUuid = null;

    /**
     * @param array<string, mixed>              $customer
     * @param array<string, mixed>              $subscriptions
     * @param array<string, array<mixed>>       $validationResults
     * @param array<string, array<int, string>> $validationTimeline
     */
    public function __construct(
        public readonly string $validationReference,
        public readonly array $customer,
        public readonly array $subscriptions,
        public array $validationResults = [],
        public array $validationTimeline = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'validation_reference' => $this->validationReference,
            'customer' => $this->customer,
            'subscriptions' => $this->subscriptions,
            'validation_results' => $this->validationResults,
            'validation_timeline' => $this->validationTimeline,
        ];
    }

    public function addValidationResult(MigrationValidationPipes $pipeline, ValidationResultInterface $result): void
    {
        $this->validationResults[$pipeline->value][] = $result->toArray();
    }

    public function addValidationTimeline(
        MigrationValidationPipes $pipeline,
        ?string $message,
        int|string|null $id = null,
    ): void {
        $trace = new Exception()->getTrace();
        /** @var array<string, string> $caller */
        $caller = array_shift($trace);

        $this->validationTimeline[$pipeline->value][] = sprintf(
            '%s File: %s Line: %s ' . ($id !== null ? 'Message: %s ID: %s' : 'Message: %s'),
            CarbonImmutable::now()->toDateTimeString('millisecond'),
            str_replace(
                '.php',
                '',
                substr($caller['file'], (int) strrpos($caller['file'], '/') + 1),
            ),
            $caller['line'],
            $message,
            $id,
        );
    }

    public function hasDomainInExtensionsArray(string $domain): bool
    {
        $domains = Arr::get($this->subscriptions, 'domain_extensions', []);
        assert(is_array($domains));

        foreach ($domains as $subscription) {
            assert(is_array($subscription));
            if (array_key_exists('domain', $subscription) && $subscription['domain'] === $domain) {
                return true;
            }
        }

        return false;
    }

    public function getBusinessUnit(): ?string
    {
        /** @var string|null $bu */
        $bu = Arr::get($this->customer, 'referenceName');

        return $bu;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSubscriptionByDomain(ImplementableProducts $type, string $domain): ?array
    {
        $subscriptions = Arr::get($this->subscriptions, $type->value, []);

        if (! is_array($subscriptions)) {
            return null;
        }

        foreach ($subscriptions as $subscription) {
            if (! is_array($subscription)) {
                return null;
            }

            if (array_key_exists('domain', $subscription) && $subscription['domain'] === $domain) {
                return $subscription;
            }
        }

        return null;
    }

    public function getJobId(): string
    {
        return $this->jobUuid ?? 'unknown';
    }

    public function setJobId(?string $jobUuid): void
    {
        $this->jobUuid = $jobUuid;
    }
}
