<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;

class CreateOneTimeServiceAction
{
    public function __construct(
        private readonly OneTimeServiceCreator $oneTimeServiceCreator,
    ) {
    }

    /**
     * @param Collection<int, OneTimeServiceContext> $contexts
     *
     * @return Collection<int, OneTimeService>
     */
    public function execute(Collection $contexts): Collection
    {
        $otsCollection = new Collection([]);

        DB::transaction(function () use ($contexts, $otsCollection) {
            $contexts->each(
                fn (OneTimeServiceContext $context) => $otsCollection->add($this->oneTimeServiceCreator->createFromContextWithNote($context))
            );
        });

        return $otsCollection;
    }
}
