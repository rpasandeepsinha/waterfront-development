<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ServicePlanGet;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    /** @var array<mixed> */
    public array $plan = [];

    private int $errorCode;

    private string $errorText;

    private string $servicePlanId;

    private string $servicePlanHostingType;

    /**
     * todo : In the faker test an initialization error occurs :
     *  - $servicePlanGuid must not be accessed before initialization.
     *    This needs to be solved, Until then, just a default value, we'll include this in ticket
     *    https://yh-jira.atlassian.net/browse/WATER-2881.
     */
    private string $servicePlanGuid = '';

    private string $servicePlanName;

    /**
     * Get the result object.
     */
    public function getResult(): Result
    {
        $result = new Result();
        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);
        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    public function getServicePlanGuid(): string
    {
        return $this->servicePlanGuid;
    }

    public function getServicePlanHostingType(): string
    {
        return $this->servicePlanHostingType;
    }

    public function getServicePlanId(): string
    {
        return $this->servicePlanId;
    }

    public function getServicePlanName(): string
    {
        return $this->servicePlanName;
    }

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        // If the HTTP response is not 200, we won't get a valid xml body to parse.
        if ($this->statusCode != 200) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->{'service-plan'}->get->result;
        }

        $status = (string) $result->status;
        $this->status = $status;

        if ($status === self::STATUS_OK) {
            /** @var array<mixed> $planArray */
            $planArray = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $this->plan = $planArray;
            $this->servicePlanId = (string) $result->id;
            $this->servicePlanGuid = (string) $result->guid;
            $this->servicePlanName = (string) $result->name;

            $this->servicePlanHostingType = $result->hosting->vrt_hst->count() === 1 ? 'vrt_hst' : 'none';

            return;
        }

        $this->errorCode = (int) $result->errcode;
        $this->errorText = (string) $result->errtext;
    }
}
