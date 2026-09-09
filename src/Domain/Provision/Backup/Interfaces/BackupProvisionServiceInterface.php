<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Interfaces;

use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUpdateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUsagesResult;
use Waterfront\Domain\Provision\Interfaces\TypeProvisionServiceInterface;

interface BackupProvisionServiceInterface extends TypeProvisionServiceInterface
{
    public function terminateBackup(TerminateBackupRequest $provisionData): BackupResult;

    public function getBackupSso(GetBackupSsoRequest $provisionData): BackupSsoResult;

    public function createBackup(CreateBackupRequest $provisionData): BackupCreateResult;

    public function updateBackup(UpdateBackupRequest $provisionData): BackupUpdateResult;

    public function setBackupSuspensionState(SetBackupSuspensionStateRequest $provisionData): BackupResult;

    public function getBackupUsages(GetBackupUsageRequest $provisionData): BackupUsagesResult;

    public function createBackupDeployment(CreateBackupDeploymentsFromMigrationRequest $provisionData): BackupCreateResult;
}
