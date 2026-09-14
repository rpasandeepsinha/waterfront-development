<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderDeploymentRepository
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return Collection<int, SitebuilderDeployment>
     */
    public function getSitebuilderDeploymentsByContext(BasekitContext $basekitContext): Collection
    {
        return $basekitContext->sitebuilderDeployments()->get();
    }

    public function create(int $requestId, string $domain): SitebuilderDeployment
    {
        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = Uuid::uuid4();
        $sitebuilderDeployment->origin_provisioning_request_id = $requestId;
        $sitebuilderDeployment->domain = $domain;
        $sitebuilderDeployment->save();

        return $sitebuilderDeployment;
    }

    /**
     * @return Collection<int, SitebuilderDeployment>
     */
    public function getByTag(UuidInterface $tag): Collection
    {
        /** @var Collection<int, SitebuilderDeployment> $deployments */
        $deployments = SitebuilderDeployment::query()->whereHas('originRequest', function ($query) use ($tag) {
            $query
                ->where('tag', $tag)
                ->where('request_type', ProvisionType::SITEBUILDER)
                ->whereIn('request_name', [
                    ProvisionRequestName::CREATE_SITEBUILDER,
                    ProvisionRequestName::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION,
                ])
                ->whereHas('result', function ($query) {
                    $query->where('status', ProvisionStatus::SUCCESS);
                });
        })->get();

        return $deployments;
    }

    public function countCreateRequestsByTag(UuidInterface $tag): int
    {
        return $this->getByTag($tag)->count();
    }

    public function findByTag(UuidInterface $tag): ?SitebuilderDeployment
    {
        return $this->getByTag($tag)->first();
    }

    /**
     * @throws Throwable
     */
    public function deleteSitebuilderAndChildren(SitebuilderDeployment $deployment): bool
    {
        return DB::transaction(function () use ($deployment): bool {
            $deletedChild = $deployment->basekitDeployment()->delete();
            if ($deletedChild === 0) {
                $this->logger->warning('Baskit deployment was not found when deleting sitebuilder deployment', [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                ]);
            }

            return (bool) $deployment->delete();
        });
    }
}
