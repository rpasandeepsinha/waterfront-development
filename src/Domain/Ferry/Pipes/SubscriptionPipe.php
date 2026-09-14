<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Apps\API\Ferry\Request\Rules\SubscriptionMigrationRules;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class SubscriptionPipe extends ValidationPipe
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly SubscriptionMigrationRules $migrationSubscriptionRules,
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validatorFactory,
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of subscription migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        // No Bu would be caught in the customerPipe.
        $bu = $payload->getBusinessUnit();

        $subscriptions = $payload->subscriptions;

        foreach (array_keys($subscriptions) as $implementableProduct) {
            ImplementableProducts::from($implementableProduct);
        }

        $rules = $this->migrationSubscriptionRules->getRules(new Customer(), true);

        $validator = $this->validatorFactory->make(
            $payload->toArray(),
            $rules,
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                messages: $exception->validator->errors()->toArray(),
            );

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'validation',
            );

            return $this->finishPipe(MigrationValidation::SUBSCRIPTION_PIPE_PASSED, $payload, $this->logger, $next);
        }

        $flattened = Arr::flatten($subscriptions, 1);

        foreach ($flattened as $subscription) {
            /** @var string $referenceProductId */
            $referenceProductId = $subscription['reference_product_id'];
            /** @var string $referenceSubscriptionId */
            $referenceSubscriptionId = $subscription['reference_subscription_id'];

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $referenceSubscriptionId,
            );

            $exists = $this->migratableSubscriptionRepository->isSubscriptionAlreadyCreated(
                $referenceSubscriptionId,
                $referenceProductId,
                $bu,
            );

            if ($exists) {
                $validationMessage = sprintf(
                    'Migration Subscription for reference Product ID: {%s} and reference Subscription ID: {%s} with migration reference: %s already exists',
                    $referenceProductId,
                    $referenceSubscriptionId,
                    $payload->validationReference,
                );

                $this->logger->debug($validationMessage, [
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                ]);
            }

            $volumeDiscountProduct = $this->productRepository->findProductsByProductGroupSlugAndProductSlug(
                productGroup: ProductGroupType::VOLUME_DISCOUNT,
                productSlug: $subscription['slug'],
            );

            if ($volumeDiscountProduct instanceof Product) {
                if ($volumeDiscountProduct->productDiscount()->doesntExist()) {
                    $validationMessage = sprintf(
                        'Migration Subscription for reference Product ID: {%s} and reference Subscription ID: {%s} with migration reference: %s is a volume discount but the waterfront product with the ID: {%d} with the grouping Volume discounts did not have a discount attached to the product.',
                        $referenceProductId,
                        $referenceSubscriptionId,
                        $payload->validationReference,
                        $volumeDiscountProduct->id,
                    );

                    $this->logger->debug($validationMessage, [
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    ]);

                    $this->addValidationResult(
                        $payload,
                        MigrationValidation::SUBSCRIPTION_VOLUME_DISCOUNT_PRODUCT_INCORRECT,
                        $validationMessage,
                    );
                }
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::SUBSCRIPTION_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::SUBSCRIPTION;
    }
}
