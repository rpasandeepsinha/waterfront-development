<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Repositories;

use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;

class RedirectDeploymentRepository
{
    public function create(
        int $requestId,
        string $source,
        string $destination,
        RedirectType $type,
        UuidInterface $context,
    ): RedirectDeployment {
        $redirectDeployment = new RedirectDeployment();
        $redirectDeployment->uuid = Uuid::uuid4();
        $redirectDeployment->source = $source;
        $redirectDeployment->destination = $destination;
        $redirectDeployment->type = $type;
        $redirectDeployment->context_uuid = $context;
        $redirectDeployment->origin_provisioning_request_id = $requestId;
        $redirectDeployment->save();

        return $redirectDeployment;
    }

    /**
     * @return Collection<int,RedirectDeployment>
     */
    public function findAllByContext(UuidInterface $contextUuid): Collection
    {
        return RedirectDeployment::where('context_uuid', $contextUuid)->get();
    }

    public function findBySourceAndContext(string $source, UuidInterface $contextUuid): ?RedirectDeployment
    {
        return RedirectDeployment::where('context_uuid', $contextUuid)->where('source', $source)->first();
    }

    public function update(
        RedirectDeployment $redirectDeployment,
        int $requestId,
        string $source,
        string $destination,
        RedirectType $type,
    ): RedirectDeployment {
        $redirectDeployment->source = $source;
        $redirectDeployment->destination = $destination;
        $redirectDeployment->type = $type;
        $redirectDeployment->origin_provisioning_request_id = $requestId;
        $redirectDeployment->save();

        return $redirectDeployment;
    }
}
