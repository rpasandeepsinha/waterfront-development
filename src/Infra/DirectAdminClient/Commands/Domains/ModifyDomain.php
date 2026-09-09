<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ModifyDomain extends DirectAdminCommand
{
    protected string $command = 'CMD_API_DOMAIN';

    protected string $method = 'POST';

    protected bool $useJsonResponse = true;

    /**
     * @var string[]
     */
    private array $domainData = [
        'action' => 'modify',
    ];

    /**
     *
     * @param string $domain The domain of the user to modify.
     */
    public function setDomain(string $domain): ModifyDomain
    {
        $this->domainData['domain'] = $domain;
        return $this;
    }

    /**
     * Setter for all domain data. This will override any existing set data. The 'action'
     * gets overwritten to da's 'modify' to keep the integrity of this command.
     *
     * @param string[] $domainData complete array of all domain data to set
     */
    public function setDomainData(array $domainData): void
    {
        $domainData['action'] = 'modify';
        $this->domainData = $domainData;
    }

    /**
     * @param string $action Action can be 'modify' or 'create'
     */
    public function setAction(string $action): ModifyDomain
    {
        $this->domainData['action'] = $action;
        return $this;
    }

    /**
     * @param string $bandwidth Amount of bandwidth User will be allowed to use. Number, in Megabytes
     */
    public function setBandwidth(string $bandwidth): ModifyDomain
    {
        $this->domainData['bandwidth'] = $bandwidth;
        return $this;
    }

    /**
     * @param string $ubandwidth ON or OFF. If ON, bandwidth is ignored and no limit is set
     */
    public function setUbandwidth(string $ubandwidth): ModifyDomain
    {
        $this->domainData['ubandwidth'] = $ubandwidth;
        return $this;
    }

    /**
     * @param string $quota Amount of disk space User will be allowed to use. Number, in Megabytes
     */
    public function setQuota(string $quota): ModifyDomain
    {
        $this->domainData['quota'] = $quota;
        return $this;
    }

    /**
     * @param string $uquota ON or OFF. If ON, quota is ignored and no limit is set
     */
    public function setUquota(string $uquota): ModifyDomain
    {
        $this->domainData['uquota'] = $uquota;
        return $this;
    }

    /**
     * @param string $ssl ON or OFF If ON, the User will have the ability to access their websites through secure https://.
     */
    public function setSsl(string $ssl): ModifyDomain
    {
        $this->domainData['ssl'] = $ssl;
        return $this;
    }

    /**
     * @param string $cgi ON or OFF If ON, the User will have the ability to run cgi scripts in their cgi-bin.
     */
    public function setCgi(string $cgi): ModifyDomain
    {
        $this->domainData['cgi'] = $cgi;
        return $this;
    }

    /**
     * @param string $php ON or OFF If ON, the User will have the ability to run php scripts.
     */
    public function setPhp(string $php): ModifyDomain
    {
        $this->domainData['php'] = $php;
        return $this;
    }

    /**
     * Create a 'Modify Domain' request to be send to the api.
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
        $params = $this->domainData;

        return Utils::streamFor(http_build_query($params));
    }
}
