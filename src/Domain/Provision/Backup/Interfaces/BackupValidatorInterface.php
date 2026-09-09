<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Interfaces;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;

interface BackupValidatorInterface extends ProvisionTypeValidatorInterface
{
    public function getBackupSsoRequestValidator(GetBackupSsoRequest $request): Validator;

    public function getCreateBackupValidator(CreateBackupRequest $createBackupRequest): ValidatorContract;

    public function updateBackupValidator(UpdateBackupRequest $request): Validator;
}
