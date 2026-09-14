<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use stdClass;
use Waterfront\Domain\AuditLogs\DTO\AuditLoggableIdentity;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class SubscriptionMutationResource
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function mutationToArray(SubscriptionMutation $subscriptionMutation): array
    {
        $subscriptionMutation->loadMissing(['product', 'subscription']);

        return [
            'id' => $subscriptionMutation->id,
            'subscription_id' => $subscriptionMutation->subscription_id,
            'product' => [
                'id' => $subscriptionMutation->product->id,
                'name' => $subscriptionMutation->product->name,
            ],
            'gross_price' => $subscriptionMutation->gross_price,
            'net_price' => $subscriptionMutation->net_price,
            'billing_period' => $subscriptionMutation->billing_period,
            'contract_period' => $subscriptionMutation->contract_period,
            'mutated_at' => $subscriptionMutation->mutated_at?->toW3cString(),
            'administratively_mutate_at' => is_null($subscriptionMutation->mutated_at)
                ? $subscriptionMutation
                    ->subscription
                    ->end_date
                    ->subDays(
                        $this->configuration->getAsInteger('constants.renewal-days'),
                    )
                    ->toW3cString()
                : null,
            'process_technical_at' => $subscriptionMutation->process_technical_at?->toW3cString(),
            'processed_technical_at' => $subscriptionMutation->processed_technical_at?->toW3cString(),
            'created_at' => $subscriptionMutation->created_at?->toW3cString(),
            'requested_by' => $this->requestedBy($subscriptionMutation),
        ];
    }

    /**
     * @param Collection<int, SubscriptionMutation > $mutations
     *
     * @return array<mixed>
     */
    public function toArray(Collection $mutations): array
    {
        $mutationsArray = [];
        foreach ($mutations as $mutation) {
            $mutationsArray[] = $this->mutationToArray($mutation);
        }

        return $mutationsArray;
    }

    /**
     * @param Collection<int,SubscriptionMutation> $mutations
     *
     * @throws JsonException
     */
    public function toJson(Collection $mutations): string
    {
        $mutationsArray = [];
        foreach ($mutations as $mutation) {
            $mutationsArray[] = $this->mutationToArray($mutation);
        }

        return json_encode($mutationsArray, flags: JSON_THROW_ON_ERROR);
    }

    private function requestedBy(SubscriptionMutation $mutation): ?AuditLoggableIdentity
    {
        $auditLog = Audit::where('auditable_type', SubscriptionMutation::class)
            ->where('auditable_id', $mutation->id)
            ->where('event', 'created')
            ->first();

        if ($auditLog !== null && $auditLog->identity_uuid !== null) {
            try {
                $identityMetadata = json_decode(
                    $auditLog->identity_metadata ?? '',
                    false,
                    flags: JSON_THROW_ON_ERROR,
                );
                assert($identityMetadata instanceof stdClass);
            } catch (JsonException) {
                return null;
            }

            return new AuditLoggableIdentity(
                $auditLog->identity_uuid,
                $identityMetadata->email ?? null,
                $identityMetadata->schemaId ?? null,
            );
        }

        return null;
    }
}
