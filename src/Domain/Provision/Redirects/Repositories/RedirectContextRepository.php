<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;

class RedirectContextRepository
{
    public function findByContext(UuidInterface $context): ?CaddyContext
    {
        return CaddyContext::query()->where('context_uuid', $context)->first();
    }

    public function findOrCreate(UuidInterface $context, string $host): CaddyContext
    {
        $existing = $this->findByContext($context);

        if ($existing !== null) {
            return $existing;
        }

        return $this->createOrRestore($context, $host);
    }

    public function createOrRestore(UuidInterface $context, string $host): CaddyContext
    {
        $caddyContext = CaddyContext::withTrashed()->where('context_uuid', $context)->first();

        if ($caddyContext === null) {
            $caddyContext = new CaddyContext();
            $caddyContext->context_uuid = $context;
        }

        $caddyContext->host = $host;

        try {
            if ($caddyContext->trashed()) {
                $caddyContext->restore();
            } else {
                $caddyContext->save();
            }
        } catch (UniqueConstraintViolationException) {
            /* Another request inserted the row between our SELECT and INSERT. This
             * can happen during the creation of the domain + subdomain redirect
             * our UNIQUE index on context_uuid guarantees only one row exists
             */
            $caddyContext = CaddyContext::withTrashed()->where('context_uuid', $context)->firstOrFail();

            if ($caddyContext->trashed()) {
                $caddyContext->restore();
            }

            if ($caddyContext->host !== $host) {
                $caddyContext->host = $host;
                $caddyContext->save();
            }

            return $caddyContext;
        }

        return $caddyContext;
    }

    public function deleteByContext(UuidInterface $context): void
    {
        CaddyContext::query()->where('context_uuid', $context)->delete();
    }
}
