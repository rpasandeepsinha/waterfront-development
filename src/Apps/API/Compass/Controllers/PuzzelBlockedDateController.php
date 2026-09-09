<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Waterfront\Apps\API\Compass\Requests\DeleteBlockedDateRequest;
use Waterfront\Apps\API\Compass\Requests\StoreBlockedDateRequest;
use Waterfront\Apps\API\Compass\Resources\Products\PuzzelBlockedDateResource;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;
use Webmozart\Assert\Assert;

class PuzzelBlockedDateController
{
    public function __construct(
        private readonly PuzzelBlockedDateRepository $blockedRepository,
    ) {
    }

    /**
     * @return AnonymousResourceCollection<PuzzelBlockedDateResource>
     */
    public function list(Request $request): AnonymousResourceCollection
    {
        $pageSize = $request->integer(key: 'pageSize', default: 100);
        $blockedDates = $this->blockedRepository->getTodayAndFutureDatesPagination($pageSize);
        $blockedDates->appends('pageSize', (string) $pageSize);
        return PuzzelBlockedDateResource::collection($blockedDates)->additional(['pageSize' => $pageSize]);
    }

    public function store(StoreBlockedDateRequest $request): Response
    {
        $date =  $request->date('date');

        Assert::isInstanceOf($date, CarbonInterface::class);

        $puzzelBlockedDate = new PuzzelBlockedDate();
        $puzzelBlockedDate->date = $date->toImmutable();
        $puzzelBlockedDate->reason = $request->string('reason')->toString();
        $puzzelBlockedDate->save();

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }

    public function destroy(DeleteBlockedDateRequest $request): Response
    {
        try {
            $this->blockedRepository->delete($request->integer('id'));
        } catch (ModelNotFoundException $exception) {
            return new Response(['message' => $exception->getMessage()], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }
}
