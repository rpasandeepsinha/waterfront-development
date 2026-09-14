<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\DTO\Callback;
use Waterfront\Infra\PuzzelClient\Enums\CiqType;

class CreateCallback extends Request implements HasBody
{
    use HasFormBody;

    public const string REDIRECT_OK = 'https://sandwave.io/ok';
    public const string REDIRECT_ERROR = 'https://sandwave.io/error';
    public const string REDIRECT_FULL = 'https://sandwave.io/full';
    public const string PUZZEL_ISO8601_NO_TIMEZONE_FORMAT = 'Y-m-d\TH:i:s';
    private const string MAX_ATTEMPTS = '1';
    private const string SECONDS_BETWEEN_ATTEMPTS = '60';

    protected Method $method = Method::POST;

    public function __construct(
        private readonly ConnectorConfig $connectorConfig,
        private readonly Callback $callback,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/contactcentre5/cow.aspx';
    }

    /**
     * @return array{
     *     customerKey: int,
     *     accessPoint: string,
     *     countryCode: string,
     *     queueKey: string,
     *     ciqType: string,
     *     maxAttempts: string,
     *     secondsBetweenAttempts: string,
     *     redirectOK: string,
     *     redirectError: string,
     *     requestDescription: string,
     *     callbackNumber: string,
     *     scheduledDateTime: string,
     *     redirectFull: string,
     * }
     */
    public function defaultBody(): array
    {
        $e164Phone = $this->callback->phoneNumber->formatE164();
        $zeroCountryCodePhone = sprintf('%s%s', '00', ltrim($e164Phone, '+'));

        return [
            'customerKey' => $this->connectorConfig->tenantId,
            'accessPoint' => $this->connectorConfig->accessPoint->number,
            'countryCode' => $this->connectorConfig->accessPoint->countryCode,
            'queueKey' => $this->connectorConfig->callbackQueue,
            'ciqType' => CiqType::CALL_AGENT_FIRST->value,
            'maxAttempts' => self::MAX_ATTEMPTS,
            'secondsBetweenAttempts' => self::SECONDS_BETWEEN_ATTEMPTS,
            'redirectOK' => self::REDIRECT_OK,
            'redirectError' => self::REDIRECT_ERROR,
            'redirectFull' => self::REDIRECT_FULL,
            'requestDescription' => $this->callback->description,
            'requestCategory' => $this->callback->category,
            'callbackNumber' => $zeroCountryCodePhone,
            'scheduledDateTime' => $this->callback->scheduledDateTime->format(self::PUZZEL_ISO8601_NO_TIMEZONE_FORMAT),
        ];
    }
}
