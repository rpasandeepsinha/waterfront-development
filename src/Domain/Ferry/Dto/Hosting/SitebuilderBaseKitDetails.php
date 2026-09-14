<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

use Illuminate\Support\Arr;

readonly class SitebuilderBaseKitDetails implements HostingDetailsInterface
{
    public function __construct(
        public int $basekitUserRef,
        public int $basekitSiteRef,
    ) {
    }

    /**
     * @param array<string, int|string> $details
     */
    public static function fromArray(array $details): HostingDetailsInterface
    {
        /** @var int $basekitUserRef */
        $basekitUserRef = Arr::get($details, 'basekit_user_ref');
        /** @var int $basekitSiteRef */
        $basekitSiteRef = Arr::get($details, 'basekit_site_ref');

        return new self(
            basekitUserRef: $basekitUserRef,
            basekitSiteRef: $basekitSiteRef,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'basekit_user_ref' => $this->basekitUserRef,
            'basekit_site_ref' => $this->basekitSiteRef,
        ];
    }

    public function getUsername(): string
    {
        return (string) $this->basekitUserRef;
    }
}
