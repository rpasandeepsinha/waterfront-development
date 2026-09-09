<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class BackupController
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSsoUrl(Subscription $subscription): JsonResponse
    {
        $backupResult = $this->backupService->getSsoUrl($subscription);

        if ($backupResult->succeeded || $backupResult->ssoUrl === null) {
            $this->logger->error(
                'Failed to generate sso url for backup',
                [
                    LoggingContextKeys::EXCEPTION => $backupResult->exception,
                ]
            );

            return new JsonResponse([
                'message' => $this->translator->translate('backup.error.sso-could-not-be-generated'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['url' => $backupResult->ssoUrl]);
    }

    public function getUsage(Subscription $subscription): JsonResponse
    {
        $usage = $this->backupService->getBackupUsage($subscription);

        if ($usage === null) {
            $this->logger->error('Failed to get tenant usage for backup');

            return new JsonResponse([
                'message' => $this->translator->translate('backup.error.usage-could-not-be-fetched'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
                'cloud_storage_gb_used' => $usage->cloudStorageGbUsed,
                'cloud_storage_gb_total' => $usage->cloudStorageGbTotal,
        ]);
    }
}
