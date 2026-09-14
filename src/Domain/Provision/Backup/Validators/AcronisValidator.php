<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Validators;

use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupRequestException;
use Waterfront\Domain\Provision\Backup\Interfaces\BackupValidatorInterface;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Rules\ShouldHaveNoExistingCreateTag;
use Waterfront\Domain\Provision\Rules\ShouldHaveOneExistingCreateTag;

class AcronisValidator implements BackupValidatorInterface
{
    public function __construct(
        private readonly Factory $validatorFactory,
    ) {
    }

    /**
     * @throws UnknownBackupRequestException
     */
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): ValidatorContract
    {
        return match ($provisionRequest::class) {
            GetBackupSsoRequest::class => $this->getBackupSsoRequestValidator($provisionRequest),
            TerminateBackupRequest::class => $this->getTerminateBackupRequestValidator($provisionRequest),
            UpdateBackupRequest::class => $this->updateBackupValidator($provisionRequest),
            SetBackupSuspensionStateRequest::class => $this->setBackupSuspensionStateValidator($provisionRequest),
            CreateBackupRequest::class => $this->getCreateBackupValidator($provisionRequest),
            GetBackupUsageRequest::class => $this->getBackupUsageRequestValidator($provisionRequest),
            default => throw new UnknownBackupRequestException($provisionRequest),
        };
    }

    public function getBackupSsoRequestValidator(GetBackupSsoRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tag->toString(),
        ];

        $rules = [
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }

    public function getTerminateBackupRequestValidator(TerminateBackupRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tagUuid->toString(),
        ];

        $rules = [
            'tag' => [
                'bail',
                'required',
                'string',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }

    public function updateBackupValidator(UpdateBackupRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tag->toString(),
            'password' => $request->password,
            'cloudStorageInGb' => $request->cloudStorageInGb,
            'localStorageInGb' => $request->localStorageInGb,
            'mobileDevices' => $request->mobileDevices,
            'workStations' => $request->workStations,
            'vms' => $request->vms,
            'servers' => $request->servers,
        ];

        $rules = [
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
            'password' => [
                'nullable',
                'string',
                Password::min(16)->letters()->mixedCase()->numbers(),
            ],
            'cloudStorageInGb' => 'nullable|numeric:strict',
            'localStorageInGb' => 'nullable|numeric:strict',
            'mobileDevices' => 'nullable|integer',
            'workStations' => 'nullable|integer',
            'vms' => 'nullable|integer',
            'servers' => 'nullable|integer',
        ];

        $validator = $this->validatorFactory->make($data, $rules);

        $validator->after(function ($validator) use ($data) {
            $hasAtLeastOne =
                $data['password'] !== null
                || $data['cloudStorageInGb'] !== null
                || $data['localStorageInGb'] !== null
                || $data['mobileDevices'] !== null
                || $data['workStations'] !== null
                || $data['vms'] !== null
                || $data['servers'] !== null;

            if (! $hasAtLeastOne) {
                $validator->errors()->add(
                    'resources',
                    'At least one resource value must be provided.',
                );
            }
        });

        return $validator;
    }

    public function setBackupSuspensionStateValidator(SetBackupSuspensionStateRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tagUuid->toString(),
            'enable' => $request->enable,
        ];

        $rules = [
            'tag' => [
                'bail',
                'required',
                'string',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
            'enable' => [
                'required',
                'boolean',
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }

    public function getCreateBackupValidator(CreateBackupRequest $createBackupRequest): ValidatorContract
    {
        $requestData = [
            'tagUuid' => $createBackupRequest->tagUuid->toString(),
            'email' => $createBackupRequest->email,
            'firstname' => $createBackupRequest->firstname,
            'lastname' => $createBackupRequest->lastname,
            'storageInGb' => $createBackupRequest->cloudStorageInGb,
            'localStorageInGb' => $createBackupRequest->localStorageInGb,
            'language' => $createBackupRequest->language->value,
            'username' => $createBackupRequest->username,
            'password' => $createBackupRequest->password,
            'mobileDevices' => $createBackupRequest->mobileDevices,
            'workStations' => $createBackupRequest->workStations,
            'servers' => $createBackupRequest->servers,
        ];

        // Remove keys that have `null` as value so we handle the 'sometimes' rule correctly.
        $requestData = array_filter($requestData, fn (mixed $value): bool => $value !== null);

        $rules = [
            'tagUuid' => ['required', 'uuid', new ShouldHaveNoExistingCreateTag($createBackupRequest->type)],
            'email' => 'required|email',
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'language' => [Rule::enum(Language::class)],
            'username' => 'sometimes|string|max:255',

            // @see https://care.acronis.com/s/article/Acronis-Cyber-Protect-Cloud-Password-Policy-Management
            'password' => [
                'sometimes',
                Password::min(16)->letters()->mixedCase()->numbers(),
            ],

            'cloudStorageInGb' => 'nullable|numeric:strict',
            'localStorageInGb' => 'nullable|numeric:strict',
            'mobileDevices' => 'nullable|integer',
            'workStations' => 'nullable|integer',
            'vms' => 'nullable|integer',
            'servers' => 'nullable|integer',
        ];

        return $this->validatorFactory->make($requestData, $rules);
    }

    public function getBackupUsageRequestValidator(GetBackupUsageRequest $request): ValidatorContract
    {
        $data = [
            'tag' => $request->tag->toString(),
        ];

        $rules = [
            'tag' => [
                'bail',
                'required',
                'uuid',
                new ShouldHaveOneExistingCreateTag($request->type),
            ],
        ];

        return $this->validatorFactory->make($data, $rules);
    }
}
