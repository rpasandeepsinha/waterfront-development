<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class ExportController
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly ResponseFactory $responseFactory,
    ) {
    }

    /**
     * Download the export file.
     */
    public function download(string $filename): BinaryFileResponse
    {
        $mimeType = $this->getMimeTypeFromFilename($filename);
        $headers = $mimeType !== null ? ['Content-Type' => $mimeType] : [];

        return $this->responseFactory
            ->download(
                $this->configuration->getAsString('filesystems.disks.private.exports') . '/' . $filename,
                headers: $headers,
            )
            ->deleteFileAfterSend();
    }

    private function getMimeTypeFromFilename(string $filename): ?string
    {
        if (! str_contains($filename, '.')) {
            return null;
        }

        $parts = explode('.', $filename);
        $extension = end($parts);

        $mimeTypesChecker = new MimeTypes();
        $mimeTypes = $mimeTypesChecker->getMimeTypes($extension);

        if ($mimeTypes === []) {
            return null;
        }

        return current($mimeTypes);
    }
}
