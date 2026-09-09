<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\VPS\Enums\JobStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;

readonly class AsynchronousCloudstackResponse
{
    /**
     * @param array<string|int, mixed>|null $result
     */
    public function __construct(
        #[SerializedName('jobid')]
        public string $jobId,
        #[SerializedName('accountid')]
        public ?string $accountId = null,
        #[SerializedName('userid')]
        public ?string $userId = null,
        public ?string $cmd = null,
        #[SerializedName('jobstatus')]
        public ?int $status = null,
        #[SerializedName('jobprocstatus')]
        public ?int $procStatus = null,
        #[SerializedName('jobresultcode')]
        public ?int $resultCode = null,
        public ?string $cloudstackCreated = null,
        #[SerializedName('jobresulttype')]
        public ?string $resultType = null,
        #[SerializedName('jobresult')]
        public ?array $result = null,
        public ?string $cloudstackCompleted = null,
        #[SerializedName('jobinstancetype')]
        public ?string $instanceType = null,
        #[SerializedName('jobinstanceid')]
        public ?string $instanceId = null,
    ) {
    }

    /**
     * @throws CloudstackNotFoundException
     *
     * @return array<string, mixed>
     */
    public function retrieveVirtualMachineData(): array
    {
        if (! $this->hasVirtualMachineData()) {
            throw new CloudstackNotFoundException(
                sprintf(
                    'No VM data availabe for job %s with status %d',
                    $this->jobId,
                    $this->status
                )
            );
        }

        /** @phpstan-ignore-next-line We know we have this key from our validation in 'hasVirtualMachineData' */
        return $this->result['virtualmachine'];
    }

    /**
     * @throws CloudstackNotFoundException
     *
     * @return array<string, mixed>
     */
    public function retrieveReinstallData(): array
    {
        if (! $this->hasReinstallData()) {
            throw new CloudstackNotFoundException(
                sprintf(
                    'No reinstall data available for job %s with status %d',
                    $this->jobId,
                    $this->status
                )
            );
        }

        /** @phpstan-ignore-next-line We know we have this key from our validation in 'hasVirtualMachineData' */
        return $this->result['virtualmachine'];
    }

    private function hasVirtualMachineData(): bool
    {
        return $this->status === JobStatus::SUCCESS->value &&
            $this->instanceType === 'VirtualMachine' &&
            $this->resultType === 'object' &&
            is_array($this->result) &&
            array_key_exists('virtualmachine', $this->result);
    }

    private function hasReinstallData(): bool
    {
        return $this->status === JobStatus::SUCCESS->value &&
            $this->resultType === 'object' &&
            is_array($this->result) &&
            array_key_exists('virtualmachine', $this->result);
    }
}
