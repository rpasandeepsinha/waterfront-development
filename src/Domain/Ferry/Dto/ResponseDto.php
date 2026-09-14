<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto;

class ResponseDto
{
    /** @var array<FailureDto> */
    private array $failures = [];

    /** @var array<SuccessDto> */
    private array $success = [];

    public function addFailure(FailureDto $failureDto): self
    {
        $this->failures[] = $failureDto;

        return $this;
    }

    public function addSuccess(SuccessDto $successDto): self
    {
        $this->success[] = $successDto;

        return $this;
    }

    public function hasFailures(): bool
    {
        return (bool) $this->failures > 0;
    }

    /**
     * @return array<string, array<int, array<string, array<string, int|string>|string>>>
     */
    public function toArray(): array
    {
        $failures = [];
        foreach ($this->failures as $dto) {
            $failures[] = $dto->toArray();
        }

        $success = [];
        foreach ($this->success as $dto) {
            $success[] = $dto->toArray();
        }

        return [
            'failures' => $failures,
            'success' => $success,
        ];
    }
}
