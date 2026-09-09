<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Propaganistas\LaravelPhone\PhoneNumber;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Puzzel\Dto\SupportCallSchedule;
use Waterfront\Domain\Puzzel\Dto\SupportCallTimeslotUsage;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackRequestRepository;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackTimeslotRepository;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\PuzzelClient\DTO\Callback;
use Waterfront\Infra\PuzzelClient\DTO\RequestInQueue;
use Waterfront\Infra\PuzzelClient\DTO\ScheduledCallbackResponse;
use Waterfront\Infra\PuzzelClient\Enums\Result;
use Waterfront\Infra\PuzzelClient\Exceptions\PuzzelResponseMissingRedirectException;
use Waterfront\Infra\PuzzelClient\PuzzelClient;
use Webmozart\Assert\Assert;

class ScheduledSupportCallService
{
    private const int WINDOW_WEEKS = 2;

    public function __construct(
        private readonly PuzzelCallbackTimeslotRepository $timeslotRepository,
        private readonly PuzzelCallbackRequestRepository $requestRepository,
        private readonly PuzzelBlockedDateRepository $blockedDateRepository,
        private readonly PuzzelClient $puzzelClient,
    ) {
    }

    public function customerExistsInQueue(Customer $customer): ?RequestInQueue
    {
        $queueItems = $this->puzzelClient->getQueueItems()->result;

        if ($queueItems === null || $queueItems === []) {
            return null;
        }

        $phoneNumber = sprintf('00%s%s%s', $customer->phone_country_code, $customer->phone_area_code, $customer->phone_subscriber_number);
        $customerItems = array_filter($queueItems, fn (RequestInQueue $item) => $item->requestRemoteAddress === $phoneNumber);

        if ($customerItems === []) {
            return null;
        }

        return $customerItems[0];
    }

    public function getScheduleForCustomer(Customer $customer): SupportCallSchedule
    {
        $now = CarbonImmutable::now();

        return new SupportCallSchedule(
            existingRequest: $this->findExistingRequestForCustomer($customer, $now) ?? $this->customerExistsInQueue($customer),
            slotsByDate: $this->getAvailableSlotsByDate($now),
        );
    }

    /**
     * @throws PuzzelResponseMissingRedirectException
     */
    public function create(
        CarbonImmutable $date,
        UuidInterface $timeslotUuid,
        string $category,
        string $description,
        Customer $customer
    ): ScheduledCallbackResponse {
        $phoneNumber = new PhoneNumber($customer->phone_number, $customer->phone_country_code);
        $timeslot = $this->timeslotRepository->getByUuid($timeslotUuid);
        Assert::notNull($timeslot);

        $scheduledDateTime = CarbonImmutable::create(
            year: $date->year,
            month: $date->month,
            day: $date->day,
            hour: $timeslot->start_timeslot->hour,
            minute: $timeslot->start_timeslot->minute
        );

        Assert::notNull($scheduledDateTime);

        $createResult = $this->puzzelClient->createCallback(
            new Callback(
                description: $description,
                category: $category,
                phoneNumber: $phoneNumber,
                scheduledDateTime: $scheduledDateTime
            )
        );

        if ($createResult->status !== Result::SUCCESS) {
            return $createResult;
        }

        $this->requestRepository->create(
            customerId: $customer->id,
            timeslotId: $timeslot->id,
            phoneNumber: $phoneNumber->getRawNumber(),
            name: $customer->contact_name,
            requestCategory: $category,
            requestDescription: $description,
            desiredCallbackTime: $scheduledDateTime
        );

        return $createResult;
    }

    private function findExistingRequestForCustomer(
        Customer $customer,
        CarbonImmutable $now,
    ): ?PuzzelCallbackRequest {
        return $this->requestRepository->findFirstFutureForCustomer($customer, $now);
    }

    /**
     * @return array<string, Collection<int, PuzzelCallbackTimeslot>>
     */
    private function getAvailableSlotsByDate(CarbonImmutable $now): array
    {
        $blockedDates = $this->blockedDateRepository->getTodayAndFutureDates();

        $startDate = $now->startOfDay();
        $endDate = $startDate
            ->addWeeks(self::WINDOW_WEEKS)
            ->subDay();

        $days = (int) $startDate->diffInDays($endDate) + 1;

        $timeslots = $this->timeslotRepository->all();

        $usageRows = $this->requestRepository->usageByDateAndTimeslot($startDate, $endDate);

        $slotsByDate = $usageRows
            ->groupBy(
                static fn (SupportCallTimeslotUsage $usage): string => $usage->date,
            )
            ->map(
                static fn (Collection $rowsForDate): Collection => $rowsForDate->mapWithKeys(
                    static fn (SupportCallTimeslotUsage $usage): array => [
                        $usage->timeslotUuid => $usage->requestsCount,
                    ],
                ),
            );

        return Collection::make()
            ->times(
                $days,
                static fn (int $offset): CarbonImmutable => $startDate->addDays($offset - 1)
            )
            ->filter(
                static fn (CarbonImmutable $date): bool => ! $date->isWeekend()
            )
            ->filter(
                static fn (CarbonImmutable $date): bool => $blockedDates->doesntContain(
                    static fn (PuzzelBlockedDate $blockedDate): bool => $blockedDate->date->isSameDay($date)
                )
            )
            ->mapWithKeys(function (CarbonImmutable $date) use ($timeslots, $slotsByDate, $now): array {
                $dateKey = $date->format(DateTimeFormat::DATE);

                $usageForDate = $slotsByDate->get($dateKey, new Collection());

                $availableSlots = $timeslots
                    ->filter(
                        fn (PuzzelCallbackTimeslot $slot): bool =>
                        $this->isSlotAvailableOnDate($slot, $date, $now, $usageForDate)
                    )
                    ->values();

                return [$dateKey => $availableSlots];
            })
            ->filter(static fn (Collection $slots): bool => $slots->isNotEmpty())
            ->all();
    }

    /**
     * @param Collection<string, int> $usageForDate
     */
    private function isSlotAvailableOnDate(
        PuzzelCallbackTimeslot $slot,
        CarbonImmutable $date,
        CarbonImmutable $now,
        Collection $usageForDate,
    ): bool {
        $usageKey = $slot->uuid->toString();

        $used = $usageForDate->get($usageKey, 0);

        if ($used >= $slot->capacity) {
            return false;
        }

        $slotDateTime = $date->setTimeFromTimeString(
            $slot->start_timeslot->format(DateTimeFormat::TIME_HMS)
        );

        return $slotDateTime->greaterThanOrEqualTo($now);
    }
}
