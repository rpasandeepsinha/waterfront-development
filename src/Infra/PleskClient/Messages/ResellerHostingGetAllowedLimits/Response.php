<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ResellerHostingGetAllowedLimits;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateResellerHosting\Result;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private int $errorCode;

    private int $resellerId;

    private string $errorText;

    /** @var mixed[] */
    private array $specsAllowed;

    public function getResellerId(): int
    {
        return $this->resellerId;
    }

    public function setResellerId(int $resellerId): void
    {
        $this->resellerId = $resellerId;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    /**
     * @return mixed[]
     */
    public function getSpecsAllowed(): array
    {
        return $this->specsAllowed;
    }

    /**
     * @throws Exception
     */
    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);
        if ($this->status === self::STATUS_OK) {
            $result->setResellerId($this->resellerId);
        } else {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    /**
     * @throws Exception
     *
     * @todo : think about right place for parsing the descriptor properties
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

        $result = $this->getNodeArray($this->xmlToArray($reply), ['reseller', 'get-limit-descriptor', 'result']);

        if (count($result) === 0) {
            $result = $this->getNodeArray($this->xmlToArray($reply), ['system']);

            if (count($result) === 0) {
                $xmlResponse = new SimpleXMLElement($reply);
                throw new PleskClientException('Could not parse resellerhosting reply ' . $xmlResponse->__toString());
            }
        }

        $this->specsAllowed = $this->getNodeArray($result, ['descriptor', 'property']);
        if (count($this->specsAllowed) === 0) {
            throw new PleskClientException('Could not parse allowed specs ');
        }

        // From this point on we know we are working with this array subset!
        /** @var array<string, string|int> $result */
        $this->setStatus(strval($result['status']));

        if ($this->status === self::STATUS_OK) {
            $this->resellerId = (int) $result['id'];
        } else {
            $this->errorCode = (int) $result['errcode'];
            $this->setErrorText((string) $result['errtext']);
        }
    }

    private function setErrorText(string $errorText): void
    {
        $this->errorText = $errorText;
    }
}
