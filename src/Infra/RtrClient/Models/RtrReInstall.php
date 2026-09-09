<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Models;

use Waterfront\Domain\Ssl\Models\Reinstall\ReInstall;

class RtrReInstall extends ReInstall
{
    public static function fromArray(array $data): ReInstall
    {
        return new self(
            main: (string) $data['main'],
            intermediate: (string) $data['intermediate'],
            root: (string) $data['root'],
            commonName: (string) $data['commonName'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'certificate' => [
                'commonName' => $this->getCommonName(),
                'certificates' => [
                    'main' => $this->getMain(),
                    'intermediate' => $this->getIntermediate(),
                    'root' => $this->getRoot(),
                ],
            ],
        ];
    }
}
