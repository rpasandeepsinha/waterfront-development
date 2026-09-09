<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Repositories;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;

class BasekitContextRepository
{
    public function findByContext(UuidInterface $context): ?BasekitContext
    {
        return BasekitContext::query()
            ->where('context_uuid', $context)
            ->first();
    }

    public function findWithTrashedByContext(UuidInterface $context): ?BasekitContext
    {
        return BasekitContext::withTrashed()
            ->where('context_uuid', $context)
            ->first();
    }

    public function create(UuidInterface $context, int $userReference): BasekitContext
    {
        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = $context;
        $basekitContext->user_ref = $userReference;
        $basekitContext->save();

        return $basekitContext;
    }

    public function delete(UuidInterface $context): bool
    {
        return (bool) BasekitContext::query()
            ->where('context_uuid', $context)
            ->delete();
    }
}
