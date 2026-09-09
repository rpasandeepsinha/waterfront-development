<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Domains\Jobs\CancelDomainSubscriptionJob;

readonly class CancelDomainDeployments
{
    public const TOO_MANY_COLUMNS_ERROR = 'Row %d has more than 1 column (has %d columns in total), while only 1 column is needed/allowed.';
    public const DRY_RUN_MSG = 'Skipping dispatching job to cancel domain "%s", because of dry run.';
    public const DISPATCH_JOB_MSG = 'Dispatching job to cancel domain "%s".';

    public function __construct(
        private Dispatcher $jobDispatcher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param resource $streamHandle
     */
    public function cancelFromStream(bool $dryRun, mixed $streamHandle): void
    {
        fgetcsv($streamHandle, null, ';', escape: '\\');
        $rowNumber = 0;

        while (! feof($streamHandle)) {
            $rowNumber++;
            $domainData = fgetcsv($streamHandle, null, ';', escape: '\\');

            if ($domainData === false) {
                break;
            }

            if (feof($streamHandle)) {
                break;
            }

            if (count($domainData) !== 1) {
                $this->logger->error(
                    sprintf(
                        self::TOO_MANY_COLUMNS_ERROR,
                        $rowNumber,
                        count($domainData)
                    )
                );

                throw new RuntimeException(
                    sprintf(
                        self::TOO_MANY_COLUMNS_ERROR,
                        $rowNumber,
                        count($domainData)
                    )
                );
            }

            $domainName = $domainData[0];
            if ($dryRun) {
                $this->logger->info(
                    sprintf(
                        self::DRY_RUN_MSG,
                        $domainName
                    )
                );
                continue;
            }

            $this->logger->info(
                sprintf(
                    self::DISPATCH_JOB_MSG,
                    $domainName
                )
            );
            $this->jobDispatcher->dispatch(
                new CancelDomainSubscriptionJob($domainName)
            );
        }
    }
}
