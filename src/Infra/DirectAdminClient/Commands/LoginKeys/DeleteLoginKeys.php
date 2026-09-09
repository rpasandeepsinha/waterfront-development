<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\LoginKeys;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DeleteLoginKeys extends DirectAdminCommand
{
    /**
     * More information: https://www.directadmin.com/features.php?id=1298.
     *
     * @var string API Command
     */
    protected string $command = 'CMD_API_LOGIN_KEYS';

    protected string $method = 'POST';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * Keys to delete from the DirectAdminServer.
     *
     * @var string[]
     */
    protected array $keys = [];

    /**
     * Add a key to delete.
     *
     * @param string $keyname Keyname from the DirectAdmin keys.
     */
    public function addKey(string $keyname): DeleteLoginKeys
    {
        $this->keys[] = $keyname;
        return $this;
    }

    /**
     * Set the array with keys to delete.
     *
     * @param string[] $keys Array containing keynames from the DirectAdmin server.
     */
    public function setKeys(array $keys): DeleteLoginKeys
    {
        $this->keys = $keys;
        return $this;
    }

    /**
     * Create a 'Login Keys' request to be send to the api.
     */
    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'delete' => 'Delete',
            'action' => 'select',
        ];

        foreach ($this->keys as $index => $keyName) {
            $params['select' . $index] = $keyName;
        }

        return Utils::streamFor(http_build_query($params));
    }
}
