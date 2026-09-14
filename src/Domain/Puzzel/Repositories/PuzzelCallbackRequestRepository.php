<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Puzzel\Dto\SupportCallTimeslotUsage;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;

class PuzzelCallbackRequestRepository
{
    public function findFirstFutureForCustomer(
        Customer $customer,
        CarbonImmutable $now,
    ): ?PuzzelCallbackRequest {
        return PuzzelCallbackRequest::query()
            ->where('customer_id', $customer->id)
            ->where('desired_callback_time', '>=', $now)
            ->orderBy('desired_callback_time')
            ->with('timeslot')
            ->first();
    }

    public function create(
        int $customerId,
        int $timeslotId,
        string $phoneNumber,
        string $name,
        string $requestCategory,
        string $requestDescription,
        CarbonImmutable $desiredCallbackTime,
    ): void {
        $callbackRequest = new PuzzelCallbackRequest();
        $callbackRequest->uuid = Uuid::uuid4();
        $callbackRequest->customer_id = $customerId;
        $callbackRequest->puzzel_callback_timeslot_id = $timeslotId;
        $callbackRequest->phone_number = $phoneNumber;
        $callbackRequest->name = $name;
        $callbackRequest->request_category = $requestCategory;
        $callbackRequest->request_description = $requestDescription;
        $callbackRequest->desired_callback_time = $desiredCallbackTime;
        $callbackRequest->save();
    }

    /**
     * @return Collection<int, SupportCallTimeslotUsage>
     */
    public function usageByDateAndTimeslot(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Collection {
        $callbackRequests = PuzzelCallbackRequest::query()
            ->join(
                'puzzel_callback_timeslots as timeslots',
                'timeslots.id',
                '=',
                'puzzel_callback_requests.puzzel_callback_timeslot_id',
            )
            ->whereBetween('puzzel_callback_requests.desired_callback_time', [
                $startDate,
                $endDate->endOfDay(),
            ])
            ->selectRaw('
                DATE(puzzel_callback_requests.desired_callback_time) as callback_date,
                timeslots.uuid as timeslot_uuid,
                COUNT(*) as usage_count
            ')
            ->groupBy('callback_date', 'timeslot_uuid')
            ->orderBy('callback_date')
            ->orderBy('timeslot_uuid')
            ->get();

        return $callbackRequests->map(
            static fn (PuzzelCallbackRequest $callbackRequest): SupportCallTimeslotUsage => new SupportCallTimeslotUsage(
                date: $callbackRequest->callback_date,
                timeslotUuid: $callbackRequest->timeslot_uuid,
                requestsCount: $callbackRequest->usage_count,
            ),
        );
    }
}
