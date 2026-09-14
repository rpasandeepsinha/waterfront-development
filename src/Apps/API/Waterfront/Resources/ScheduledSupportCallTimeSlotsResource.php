<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Puzzel\Dto\SupportCallSchedule;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\PuzzelClient\DTO\RequestInQueue;
use Webmozart\Assert\Assert;

/**
 * @param Request $request
 *
 * @return array{
 *     existingRequest: array<string, mixed>|null,
 *     availableSlots: array<string, array<int, array{uuid: string, display: string}>>
 * }
 */
class ScheduledSupportCallTimeSlotsResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array{
     *     existingRequest: array<string, mixed>|null,
     *     availableSlots: array<string, array<int, array{uuid: string, display: string}>>
     * }
     */
    public function toArray($request): array
    {
        /** @var SupportCallSchedule $schedule */
        $schedule = $this->resource;

        $slotsByDate = $schedule->slotsByDate;

        $scheduledRequest = $this->formatScheduledRequest($schedule->existingRequest);

        $availableSlots = [];

        foreach ($slotsByDate as $date => $slots) {
            $daySlots = ScheduledSupportCallTimeslotResource::collection($slots)->resolve();
            $availableSlots[$date] = $daySlots;
        }

        return [
            'existingRequest' => $scheduledRequest,
            'availableSlots' => $availableSlots,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatScheduledRequest(PuzzelCallbackRequest|RequestInQueue|null $request): ?array
    {
        if ($request === null) {
            return null;
        }

        return match (true) {
            $request instanceof PuzzelCallbackRequest => $this->formatFromPuzzelCallbackRequest($request),
            $request instanceof RequestInQueue => $this->formatFromRequestInQueue($request),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFromPuzzelCallbackRequest(PuzzelCallbackRequest $request): array
    {
        $timeslot = $request->timeslot;

        return [
            'date' => $request->desired_callback_time->toIso8601String(),
            'startTime' => $timeslot->start_timeslot->format(DateTimeFormat::TIME_HM),
            'endTime' => $timeslot->end_timeslot->format(DateTimeFormat::TIME_HM),
            'phoneNumber' => $request->phone_number,
        ];
    }

    /**
     * If the scheduled callback is in the past in our database but the
     * Puzzel queue still has the callback we format it as best as we
     * can to a full hour and make it last 30m from the Puzzel data.
     *
     * @return array<string, mixed>
     */
    private function formatFromRequestInQueue(RequestInQueue $request): array
    {
        $now = CarbonImmutable::now();
        $dateTime = $request->callbackScheduledTime === null
            ? $now
            : CarbonImmutable::make($request->callbackScheduledTime);

        Assert::notNull($dateTime);

        return [
            'date' => $dateTime->toIso8601String(),
            'startTime' => $dateTime->roundUnit('hour')->format(DateTimeFormat::TIME_HM),
            'endTime' => $dateTime->roundUnit('hour')->addMinutes(30)->format(DateTimeFormat::TIME_HM),
            'phoneNumber' => $request->requestRemoteAddress,
        ];
    }
}
