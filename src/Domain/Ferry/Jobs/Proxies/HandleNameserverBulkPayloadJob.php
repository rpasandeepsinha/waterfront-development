<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\Proxies;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Domains\ExecuteNameserverMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryJobRequester;
use Waterfront\Domain\Ferry\Jobs\AzureDataFactory\AzureDataFactoryMessage;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HandleNameserverBulkPayloadJob extends AbstractQueueableJob
{
    public int $timeout = 900;

    /**
     * @param array<int, array<mixed>> $nameserverPayloads
     */
    public function __construct(
        private readonly array $nameserverPayloads,
    ) {
        parent::__construct();
    }

    public function handle(
        Dispatcher $dispatcher,
        LoggerInterface $logger,
        MigratableSubscriptionRepository $migratableSubscriptionRepository,
        SubscriptionMigrationValidator $subscriptionMigrationValidator,
        ResponseDto $responseDto,
        AdfPayloadService $adfPayloadService,
        ExecuteNameserverMigrationAction $executeNameserverMigrationAction,
    ): void {
        $amount = count($this->nameserverPayloads);

        $logger->info('Starting to insert bulk nameserver migration jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'customer_amount' => $amount,
            ],
        ]);

        foreach ($this->nameserverPayloads as $key => $configureDnsPayload) {
            $waterfrontCustomerId = $this->getAsInteger($configureDnsPayload, 'waterfront_customer_id');

            $customer = Customer::find($waterfrontCustomerId);
            if (! $customer instanceof Customer) {
                $logger->warning('Unknown customer id in ferry nameserver bulk migrations proxy job. Skipping this payload', [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                    LoggingContextKeys::CUSTOMER_ID => $waterfrontCustomerId,
                    LoggingContextKeys::META => [
                        'array_key' => $key,
                    ],
                ]);
                continue;
            }

            $migratedCustomer = $customer->migratedCustomers->first();
            if (! $migratedCustomer instanceof MigratedCustomer) {
                $logger->warning(
                    'No migrated customer found for existing customer in ferry nameserver bulk migrations proxy job. Was this customer ID part of the migration? Skipping this payload.',
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                        LoggingContextKeys::CUSTOMER_ID => $waterfrontCustomerId,
                        LoggingContextKeys::META => [
                            'array_key' => $key,
                        ],
                    ],
                );
                continue;
            }

            $subscriptions = $this->gatherEligibleSubscriptionsForCustomer(
                customer: $customer,
                migratableSubscriptionRepository: $migratableSubscriptionRepository,
                subscriptionMigrationValidator: $subscriptionMigrationValidator,
                responseDto: $responseDto,
            );

            if (! $subscriptions->isEmpty()) {
                $executeNameserverMigrationAction->execute($subscriptions);
                $responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));
            }

            $validationPayload = ['validation' => $responseDto->toArray()];
            $messageType = AzureDataFactoryMessageType::MIGRATION_BULK_CUSTOMER_REPORT->value;

            $dispatcher->dispatch(
                new AzureDataFactoryJobRequester(
                    message: AzureDataFactoryMessage::create(
                        $messageType,
                        [
                            ...$adfPayloadService
                                ->fetchTechnicalMigrationBulkCustomerStatePayload(
                                    customer: $customer,
                                    migratedCustomer: $migratedCustomer,
                                    migrationStep: MigrationStep::NAMESERVER,
                                )
                                ->toArray(),
                            ...$validationPayload,
                        ],
                    ),
                    reference: $migratedCustomer->reference_customer_number,
                ),
            );
        }

        $logger->info('Completed inserting bulk nameserver migration jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'customer_amount' => $amount,
            ],
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_PROXY;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function gatherEligibleSubscriptionsForCustomer(
        Customer $customer,
        MigratableSubscriptionRepository $migratableSubscriptionRepository,
        SubscriptionMigrationValidator $subscriptionMigrationValidator,
        ResponseDto $responseDto,
    ): Collection {
        return $migratableSubscriptionRepository
            ->getSubscriptionsForNameserverMigration($customer)
            ->filter(function ($subscription) use ($customer, $subscriptionMigrationValidator, $responseDto) {
                try {
                    $subscriptionMigrationValidator->validateEligibleForDomainMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Nameserver migration step not allowed for subscription: %s', $e->getMessage()),
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
            'Created jobs to configure nameservers for every eligible subscription',
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create(
                    'subscriptionIds',
                    $subscriptions
                        ->map(fn ($s) => $s->id)
                        ->sort()
                        ->join(','),
                ),
            ],
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsInteger(array $payload, string $key): int
    {
        $value = Arr::get($payload, $key, 0);

        if (! is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Value for %s needs to be a number',
                    $key,
                ),
            );
        }

        return (int) $value;
    }
}
