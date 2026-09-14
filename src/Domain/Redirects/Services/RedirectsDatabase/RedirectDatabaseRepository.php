<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services\RedirectsDatabase;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

/**
 * @mixin Builder<RedirectDatabaseRepository>
 *
 * @internal
 *
 * @property-read int $id
 * @property-read int $klantid
 * @property-read string $domein
 * @property-read string $extensie
 * @property-read string $domainname
 * @property string $destination
 * @property-read ?int $cloaked
 * @property-read ?string $title
 * @property string $type
 */
#[Connection('redirects')]
#[Guarded(['id'])]
#[WithoutTimestamps]
class RedirectDatabaseRepository extends Model implements RedirectsRepositoryInterface
{
    protected $table = 'redirects';

    /** @return Redirect[] */
    public function listRedirects(int $customerId, string $domain): array
    {
        [, $domainBody, $extension] = Redirect::parseHost($domain);

        /** @var Redirect[] $redirects */
        $redirects = self::where('klantid', $customerId)
            ->where('domein', $domainBody)
            ->where('extensie', $extension)
            ->get()
            ->map(fn (self $model): Redirect => $model->toDomainObject())
            ->toArray();

        return $redirects;
    }

    public function createRedirect(int $customerId, string $source, string $destination, string $type): Redirect
    {
        [$subDomain, $domainBody, $extension] = Redirect::parseHost($source);

        /** @var RedirectDatabaseRepository $model */
        $model = self::create([
            'klantid' => $customerId,
            'domein' => $domainBody,
            'extensie' => $extension,
            'domainname' => $subDomain === ''
                ? "{$domainBody}.{$extension}"
                : "{$subDomain}.{$domainBody}.{$extension}",
            'destination' => $destination,
            'type' => $type,
        ]);

        return $model->toDomainObject();
    }

    public function updateRedirect(int $customerId, string $source, string $destination, string $type): Redirect
    {
        $model = $this->findBySource($customerId, $source);

        if ($model !== null) {
            $model->fill([
                'destination' => $destination,
                'type' => $type,
            ])->save();

            return $model->toDomainObject();
        }

        return $this->createRedirect($customerId, $source, $destination, $type);
    }

    public function deleteRedirect(int $customerId, string $source): void
    {
        $model = $this->findBySource($customerId, $source);
        $model?->delete();
    }

    public function toDomainObject(): Redirect
    {
        return new Redirect(
            $this->klantid,
            $this->domein,
            $this->extensie,
            $this->domainname,
            $this->destination,
            RedirectType::from($this->type)->value,
        );
    }

    public function isRedirectSourceUnique(int $customerId, string $source): bool
    {
        return is_null($this->findBySource($customerId, $source));
    }

    public function findBySourceForMigrations(string $source): ?RedirectDatabaseRepository
    {
        [$subDomain, $domainBody, $extension] = Redirect::parseHost($source);

        return self::where('domein', $domainBody)
            ->where('extensie', $extension)
            ->where(
                'domainname',
                $subDomain === '' ? "{$domainBody}.{$extension}" : "{$subDomain}.{$domainBody}.{$extension}",
            )
            ->first();
    }

    private function findBySource(int $customerId, string $source): ?RedirectDatabaseRepository
    {
        [$subDomain, $domainBody, $extension] = Redirect::parseHost($source);

        return self::where('klantid', $customerId)
            ->where('domein', $domainBody)
            ->where('extensie', $extension)
            ->where(
                'domainname',
                $subDomain === '' ? "{$domainBody}.{$extension}" : "{$subDomain}.{$domainBody}.{$extension}",
            )
            ->first();
    }
}
