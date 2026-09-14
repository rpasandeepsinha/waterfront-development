<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ModifyReseller extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_MODIFY_RESELLER';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    /**
     * @var bool set the response to be json
     */
    protected bool $useJsonResponse = true;

    /**
     * @var array<string,string> containing data to be modified for this reseller.
     */
    private $resellerData = [
        'action' => 'customize',
    ];

    /**
     * This method will be called when the command received a response from the server.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        parent::responseReceived($decodedContent);

        $this->succeeded = false;

        if (
            array_key_exists('success', $decodedContent)
            && $decodedContent['success'] === 'Options changed successfully'
        ) {
            $this->result = $decodedContent['success'];
            $this->succeeded = true;
        }

        return $this;
    }

    /**
     * @param string $email A valid email address
     */
    public function setEmail(string $email): ModifyReseller
    {
        $this->resellerData['email'] = $email;

        return $this;
    }

    /**
     * @param string $passwd The User's password. 5+ characters, ascii
     */
    public function setPasswd(string $passwd): ModifyReseller
    {
        $this->resellerData['passwd'] = $passwd;

        return $this;
    }

    /**
     * @param string $loginKeys ON or OFF to allow the user to have or create Login Keys.
     */
    public function setLoginKeys(string $loginKeys): ModifyReseller
    {
        $this->resellerData['login_keys'] = $loginKeys;

        return $this;
    }

    /**
     * @param string $domain A valid domain name in the form: domain.com
     */
    public function setDomain(string $domain): ModifyReseller
    {
        $this->resellerData['domain'] = $domain;

        return $this;
    }

    /**
     * @param string $package One of the User packages created by the Reseller
     */
    public function setPackage(string $package): ModifyReseller
    {
        $this->resellerData['package'] = $package;

        return $this;
    }

    /**
     * @param string $ip 'shared' or 'assign'.
     *                   If shared, domain will use the server's main ip. assign will use one of the reseller's ips
     */
    public function setIp(string $ip): ModifyReseller
    {
        $this->resellerData['ip'] = $ip;

        return $this;
    }

    /**
     * @param string $notify yes or no. If yes, an email will be sent to email
     */
    public function setNotify(string $notify): ModifyReseller
    {
        $this->resellerData['notify'] = $notify;

        return $this;
    }

    public function setBandwidth(string $bandwidth): ModifyReseller
    {
        $this->resellerData['bandwidth'] = $bandwidth;

        return $this;
    }

    public function setUbandwidth(string $ubandwidth): ModifyReseller
    {
        $this->resellerData['ubandwidth'] = $ubandwidth;

        return $this;
    }

    public function setQuota(string $quota): ModifyReseller
    {
        $this->resellerData['quota'] = $quota;

        return $this;
    }

    public function setUquota(string $uquota): ModifyReseller
    {
        $this->resellerData['uquota'] = $uquota;

        return $this;
    }

    public function setVdomains(string $vdomains): ModifyReseller
    {
        $this->resellerData['vdomains'] = $vdomains;

        return $this;
    }

    public function setUvdomains(string $uvdomains): ModifyReseller
    {
        $this->resellerData['uvdomains'] = $uvdomains;

        return $this;
    }

    public function setNsubdomains(string $nsubdomains): ModifyReseller
    {
        $this->resellerData['nsubdomains'] = $nsubdomains;

        return $this;
    }

    public function setUnsubdomains(string $unsubdomains): ModifyReseller
    {
        $this->resellerData['unsubdomains'] = $unsubdomains;

        return $this;
    }

    public function setIps(string $ips): ModifyReseller
    {
        $this->resellerData['ips'] = $ips;

        return $this;
    }

    public function setNemails(string $nemails): ModifyReseller
    {
        $this->resellerData['nemails'] = $nemails;

        return $this;
    }

    public function setUnemails(string $unemails): ModifyReseller
    {
        $this->resellerData['unemails'] = $unemails;

        return $this;
    }

    public function setNemailf(string $nemailf): ModifyReseller
    {
        $this->resellerData['nemailf'] = $nemailf;

        return $this;
    }

    public function setUnemailf(string $unemailf): ModifyReseller
    {
        $this->resellerData['unemailf'] = $unemailf;

        return $this;
    }

    public function setNemailml(string $nemailml): ModifyReseller
    {
        $this->resellerData['nemailml'] = $nemailml;

        return $this;
    }

    public function setUnemailml(string $unemailml): ModifyReseller
    {
        $this->resellerData['unemailml'] = $unemailml;

        return $this;
    }

    public function setNemailr(string $nemailr): ModifyReseller
    {
        $this->resellerData['nemailr'] = $nemailr;

        return $this;
    }

    public function setUnemailr(string $unemailr): ModifyReseller
    {
        $this->resellerData['unemailr'] = $unemailr;

        return $this;
    }

    public function setMysql(string $mysql): ModifyReseller
    {
        $this->resellerData['mysql'] = $mysql;

        return $this;
    }

    public function setUmysql(string $umysql): ModifyReseller
    {
        $this->resellerData['umysql'] = $umysql;

        return $this;
    }

    public function setDomainptr(string $domainptr): ModifyReseller
    {
        $this->resellerData['domainptr'] = $domainptr;

        return $this;
    }

    public function setUdomainptr(string $udomainptr): ModifyReseller
    {
        $this->resellerData['udomainptr'] = $udomainptr;

        return $this;
    }

    public function setFtp(string $ftp): ModifyReseller
    {
        $this->resellerData['ftp'] = $ftp;

        return $this;
    }

    public function setUftp(string $uftp): ModifyReseller
    {
        $this->resellerData['uftp'] = $uftp;

        return $this;
    }

    public function setAftp(string $aftp): ModifyReseller
    {
        $this->resellerData['aftp'] = $aftp;

        return $this;
    }

    public function setPhp(string $php): ModifyReseller
    {
        $this->resellerData['php'] = $php;

        return $this;
    }

    public function setCgi(string $cgi): ModifyReseller
    {
        $this->resellerData['cgi'] = $cgi;

        return $this;
    }

    public function setSsl(string $ssl): ModifyReseller
    {
        $this->resellerData['ssl'] = $ssl;

        return $this;
    }

    public function setSsh(string $ssh): ModifyReseller
    {
        $this->resellerData['ssh'] = $ssh;

        return $this;
    }

    public function setUserssh(string $userssh): ModifyReseller
    {
        $this->resellerData['userssh'] = $userssh;

        return $this;
    }

    public function setDnscontrol(string $dnscontrol): ModifyReseller
    {
        $this->resellerData['dnscontrol'] = $dnscontrol;

        return $this;
    }

    /**
     * @param string $dns OFF or TWO or THREE.
     *                    If OFF, no dns's will be created.
     *                    TWO: domain ip for ns1 and another ip for ns2.
     *                    THREE: domain has own ip. ns1 and ns2 have their own ips
     */
    public function setDns(string $dns): ModifyReseller
    {
        $this->resellerData['dns'] = $dns;

        return $this;
    }

    /**
     * @param string $reseller the reseller name of the Reseller to be modified
     */
    public function setReseller(string $reseller): ModifyReseller
    {
        /**
         * Directadmin API still calls this 'user' even though it modifies a reseller.
         *
         * @see https://www.directadmin.com/features.php?id=829
         */
        $this->resellerData['user'] = $reseller;

        return $this;
    }

    /**
     * @param string $serverip ON or OFF
     *                         If ON, the reseller will have the ability to create users using the servers main ip.
     */
    public function setServerip(string $serverip): ModifyReseller
    {
        $this->resellerData['serverip'] = $serverip;

        return $this;
    }

    public function setNusers(string $nusers): ModifyReseller
    {
        $this->resellerData['nusers'] = $nusers;

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
        $params = $this->resellerData;

        return Utils::streamFor(http_build_query($params));
    }
}
