<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\MailOnlyMigrationRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\ExecuteTechnicalMailOnlyMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Mappers\MailOnlyMapper;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MailOnlyMigrationController
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private readonly ExecuteTechnicalMailOnlyMigrationAction $executeTechnicalMailOnlyMigrationAction,
        private readonly MailOnlyMapper $mailOnlyMapper,
        private readonly ResponseDto $responseDto,
    ) {
    }

    public function execute(MailOnlyMigrationRequest $request, Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository->getSubscriptionsForMailOnlyMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForMailOnlyMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));
                    return false;
                }
                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        $hostingMigrationPayloads = $this->getMailOnlyMigrationPayloads($subscriptions, $request->validated());

        $this->executeTechnicalMailOnlyMigrationAction->execute($hostingMigrationPayloads);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(NotEligibleForMigrationException $e, Customer $customer, Subscription $subscription): FailureDto
    {
        return FailureDto::create(
            sprintf('Mail only migration step not allowed for subscription: %s', $e->getMessage()),
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create('subscriptionId', $subscription->id),
            ],
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function makeSuccessDto(Customer $customer, Collection $subscriptions): SuccessDto
    {
        return SuccessDto::create(
            'Created jobs to migrate mail only for every eligible subscription',
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create('subscriptionIds', $subscriptions->map(fn (Subscription $subscription) => $subscription->id)->sort()->join(',')),
            ],
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     * @param array<mixed>                  $validated
     *
     * @return array<int, HostingMigrationPayload>
     */
    private function getMailOnlyMigrationPayloads(Collection $subscriptions, array $validated): array
    {
        $technicalPayloads = $this->mailOnlyMapper->filterEligiblePayloads($subscriptions, $validated);

        $hostingMigrationPayloads = [];
        foreach ($technicalPayloads as $mappablePayload) {
            $hostingMigrationPayloads[] = $this->mailOnlyMapper
                ->mapSubscriptionsWithConnectionDetails($subscriptions, $mappablePayload);
        }

        return $hostingMigrationPayloads;
    }
}
