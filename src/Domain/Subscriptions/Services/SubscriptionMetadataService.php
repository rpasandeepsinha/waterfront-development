<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionCategories;
use Waterfront\Support\Enums\LoggingContextKeys;

class SubscriptionMetadataService
{
    public function __construct(
        private readonly LighthouseApiService $lighthouseApiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function assignCategory(Subscription $subscription, SubscriptionCategory|null $category): void
    {
        $categoryModel = $subscription->category ?? new SubscriptionCategories();

        if (! $categoryModel->exists) {
            $categoryModel->subscription_id = $subscription->id;
        }

        $categoryModel->name = $category;
        $categoryModel->save();
    }

    public function assignEmployee(Subscription $subscription, UuidInterface|null $assigneeUuid): void
    {
        $category = $subscription->category ?? new SubscriptionCategories();

        if (! $category->exists) {
            $category->subscription_id = $subscription->id;
        }

        if ($category->assignee_metadata !== null && $assigneeUuid !== null && $category->assignee_metadata->uuid->toString() === $assigneeUuid->toString()) {
            return;
        }

        if ($assigneeUuid === null) {
            $category->assignee_metadata = null;
        } else {
            try {
                $identity = $this->lighthouseApiService->getKratosIdentityByIdentifier($assigneeUuid->toString());
            } catch (ResourceNotFoundException | LighthouseException | JsonException $e) {
                $this->logger->info('Failed to retrieve required information', [
                    LoggingContextKeys::EXCEPTION => $e->getMessage(),
                    LoggingContextKeys::META => ['identifier' => $assigneeUuid->toString()],
                ]);
                throw new RuntimeException('Failed to retrieve required information');
            }

            if ($identity->schemaId !== SchemaId::EMPLOYEE) {
                // Generic exception because, so we don't show our hand (poker reference).
                throw new RuntimeException('Failed to retrieve required information');
            }

            $assigneeMetadata = new IdentityMetadataDTO(Uuid::fromString($identity->id), $identity->traits->email);
            $category->assignee_metadata = $assigneeMetadata;
        }

        $category->save();
    }
}
