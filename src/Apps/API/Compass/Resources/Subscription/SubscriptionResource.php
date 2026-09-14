<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Waterfront\Apps\API\Compass\Resources\Products\ProductPresenter;
use Waterfront\Apps\API\Compass\Resources\RespectsExcludedFields;
use Waterfront\Apps\API\Compass\Resources\RetentionToolkit\CustomerRetentionOfferResource;
use Waterfront\Apps\API\Compass\Support\FieldDefinition;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Customers\Services\ExperimentService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    use RespectsExcludedFields;

    /**
     * Defines all fields that can be requested via `fields[]`.
     *
     * Each entry maps a frontend field name to:
     *   - `column` — the DB column it requires on the subscriptions table (null = relation / computed)
     *   - `resolve` — closure that serializes the field value from the model
     *
     * This is the single source of truth consumed by both this Resource (serialization)
     * and SubscriptionFilter (SELECT restriction).
     *
     * @return array<string, FieldDefinition<Subscription>>
     */
    public static function fieldDefinitions(): array
    {
        /**
         * TODO rewrite this class to a presenter to allow dependency injection.
         *
         * @var ProductPresenter $productPresenter
         *
         * @phpstan-ignore disallowed.function
         */
        $productPresenter = resolve(ProductPresenter::class);

        return [
            'customer_number' => new FieldDefinition(null, fn (Subscription $s) => $s->customer->customer_number),
            'domain' => new FieldDefinition('domain', fn (Subscription $s) => $s->domain),
            'administrative_status' => new FieldDefinition(
                'administrative_status',
                fn (Subscription $s) => $s->administrative_status,
            ),
            'technical_status' => new FieldDefinition('technical_status', fn (Subscription $s) => $s->technical_status),
            'product' => new FieldDefinition(null, fn (Subscription $s) => $productPresenter->toArray($s->product)),
            'provider' => new FieldDefinition(
                null,
                fn (Subscription $s) => $s->getDeployments()->first()->provider->slug ?? null,
            ),
            'start_date' => new FieldDefinition('start_date', fn (Subscription $s) => $s->start_date->toW3cString()),
            'end_date' => new FieldDefinition('end_date', fn (Subscription $s) => $s->end_date->toW3cString()),
            'mutation_count' => new FieldDefinition(
                null,
                fn (Subscription $s) => (
                    $s->pending_mutations_count ?? $s->mutations()->whereNull('mutated_at')->count()
                ),
            ),
            'billing_period' => new FieldDefinition('billing_period', fn (Subscription $s) => $s->billing_period),
            'contract_period' => new FieldDefinition('contract_period', fn (Subscription $s) => $s->contract_period),
            'net_price' => new FieldDefinition('net_price', fn (Subscription $s) => $s->net_price),
            'gross_price' => new FieldDefinition('gross_price', fn (Subscription $s) => $s->gross_price),
            'category' => new FieldDefinition(null, fn (Subscription $s) => $s->category?->only([
                'name',
                'assignee_metadata',
            ])),
            'retention_offers' => new FieldDefinition(
                null,
                fn (Subscription $s) => $s->relationLoaded('retentionOffers')
                    ? CustomerRetentionOfferResource::collection($s->retentionOffers)
                    : new MissingValue(),
            ),
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        $proxy = new FieldSelectionProxy($request, static::fieldDefinitions());

        $data = array_merge(
            ['id' => $this->resource->id, 'uuid' => $this->resource->uuid],
            $proxy->resolve($this->resource),
        );

        if (! $this->fieldExcluded($request, 'available_actions')) {
            $subscriptionPolicy = Container::getInstance()->make(SubscriptionPolicy::class);
            $data['available_actions'] = $subscriptionPolicy->getAvailableActions($this->resource);
        }

        if (! $this->fieldExcluded($request, 'labels')) {
            $experimentService = Container::getInstance()->make(ExperimentService::class);
            $data['labels'] = [
                'experiments' => $experimentService->subscriptionParticipatesInExperiments($this->resource),
            ];
        }

        return $data;
    }
}
