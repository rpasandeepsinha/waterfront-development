<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class CreateUser extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_ACCOUNT_USER';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    /**
     * /**
     * @var string The User's username. 4-8 characters, alphanumeric
     */
    private string $username = '';

    /**
     * @var string A valid email address
     */
    private string $email = '';

    /**
     * @var string The User's password. 5+ characters, ascii
     */
    private string $passwd = '';

    /**
     * @var string A valid domain name in the form: domain.com
     */
    private string $domain = '';

    /**
     * @var string One of the User packages created by the Reseller
     */
    private string $package = '';

    /**
     * @var string One of the ips which is available for user creation. Only free or shared ips are allowed.
     */
    private string $ip = '';

    /**
     * @var string yes or no. If yes, an email will be sent to email
     */
    private string $notify = '';

    /**
     * @var string ON or OFF. If ON , ssl will be enabled
     */
    private string $sslEnabled = '';

    /**
     * @return string The User's username. 4-8 characters, alphanumeric
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username The User's username. 4-8 characters, alphanumeric
     */
    public function setUsername(string $username): CreateUser
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string A valid email address
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email A valid email address
     */
    public function setEmail(string $email): CreateUser
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string The User's password. 5+ characters, ascii
     */
    public function getPasswd(): string
    {
        return $this->passwd;
    }

    /**
     * @param string $passwd The User's password. 5+ characters, ascii
     */
    public function setPasswd(string $passwd): CreateUser
    {
        $this->passwd = $passwd;

        return $this;
    }

    /**
     * @return string A valid domain name in the form: domain.com
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain A valid domain name in the form: domain.com
     */
    public function setDomain(string $domain): CreateUser
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return string One of the User packages created by the Reseller
     */
    public function getPackage(): string
    {
        return $this->package;
    }

    /**
     * @param string $package One of the User packages created by the Reseller
     */
    public function setPackage(string $package): CreateUser
    {
        $this->package = $package;

        return $this;
    }

    /**
     * @return string One of the ips which is available for user creation. Only free or shared ips are allowed.
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @param string $ip One of the ips which is available for user creation. Only free or shared ips are allowed.
     */
    public function setIp(string $ip): CreateUser
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return string yes or no. If yes, an email will be sent to email
     */
    public function getNotify(): string
    {
        return $this->notify;
    }

    /**
     * @param string $notify yes or no. If yes, an email will be sent to email
     */
    public function setNotify(string $notify): CreateUser
    {
        $this->notify = $notify;

        return $this;
    }

    /**
     * @return string ON or OFF. If ON ssl will be enabled for the domain
     */
    public function getSslEnabled(): string
    {
        return $this->sslEnabled;
    }

    public function setSslEnabled(string $sslEnabled): CreateUser
    {
        $this->sslEnabled = $sslEnabled;

        return $this;
    }

    /**
     * Create a 'Create account' request to be send to the api.
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
            'action' => 'create',
            'add' => 'submit',
            'username' => $this->getUsername(),
            'email' => $this->getEmail(),
            'passwd' => $this->getPasswd(),
            'passwd2' => $this->getPasswd(),
            'domain' => $this->getDomain(),
            'package' => $this->getPackage(),
            'ip' => $this->getIp(),
            'notify' => $this->getNotify(),
            'ssl' => $this->getSslEnabled(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
