<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ServicePlanIndex;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    /** @var array<int, string> */
    public array $plans = [];

    private int $errorCode;

    private string $errorText;

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

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        // If the HTTP response is not 200, we won't get a valid xml body to parse.
        if ($this->statusCode !== 200) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        $plans = [];

        if ($result->count() === 0) {
            $result = $xmlResponse->{'service-plan'}->get->result;
        }

        $status = (string) $result->status;
        $this->status = $status;

        if ($status === self::STATUS_OK) {
            foreach ($result as $resultItem) {
                $plans[] = $resultItem->name->__toString();
            }

            $this->plans = $plans;

            return;
        }

        $this->errorCode = (int) $result->errcode;
        $this->errorText = (string) $result->errtext;
    }
}
