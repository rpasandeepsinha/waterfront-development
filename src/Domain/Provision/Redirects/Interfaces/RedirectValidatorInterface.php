<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Interfaces;

use Illuminate\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;

interface RedirectValidatorInterface extends ProvisionTypeValidatorInterface
{
    public function getCreateRedirectValidator(CreateRedirectRequest $createRedirectRequest): ValidatorContract;

    public function getRedirectValidator(GetRedirectRequest $getRedirectRequest): ValidatorContract;

    public function getListRedirectsValidator(ListRedirectsRequest $getRedirectsByContextRequest): ValidatorContract;

    public function getUpdateRedirectValidator(UpdateRedirectRequest $updateRedirectRequest): ValidatorContract;

    public function getDeleteRedirectValidator(DeleteRedirectRequest $deleteRedirectRequest): ValidatorContract;

    public function getTerminateRedirectsValidator(TerminateRedirectsRequest $terminateRedirectsRequest): ValidatorContract;
}
