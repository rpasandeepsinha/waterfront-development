<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\LoginKeys;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowLoginKeys extends DirectAdminCommand
{
    /**
     * @var string API Command
     */
    protected string $command = 'CMD_API_LOGIN_KEYS';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * Collection of keys retrieved from the API.
     *
     * @var mixed[][]
     */
    private array $loginKeys = [];

    /**
     * Get the login keys after the API call.
     *
     * @return mixed[][]
     */
    public function getLoginKeys(): array
    {
        return $this->loginKeys;
    }

    /**
     * Get a single key after the api call.
     *
     * @param string $keyName name of the key to retrieve
     *
     * @return mixed[]
     */
    public function getLoginKey(string $keyName): array
    {
        return $this->loginKeys[$keyName];
    }

    /**
     * Override the responseReceived method to set the keys from the API.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        foreach ($decodedContent as $keyName => $keySettings) {
            assert(is_string($keySettings));
            parse_str($keySettings, $parsedSettings);
            $parsedSettings['keyname'] = $keyName;
            $this->loginKeys[$keyName] = $parsedSettings;
        }

        return parent::responseReceived($decodedContent);
    }
}
