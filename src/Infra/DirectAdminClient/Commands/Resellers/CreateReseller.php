<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class CreateReseller extends DirectAdminCommand
{
    /**
     * @var string from DirectAdminApi
     */
    protected string $command = 'CMD_API_ACCOUNT_RESELLER';

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
     * @var string shared, sharedreseller or assign. If shared, domain will use the server's main ip. assign will use one of the reseller's ips. sharedreseller will set the resellers ip to shared and assign the reseller to it.
     */
    private string $ip = '';

    /**
     * @var string yes or no. If yes, an email will be sent to email
     */
    private string $notify = '';

    /**
     * @var string Amount of bandwidth Reseller will be allowed to use. Number, in Megabytes
     */
    private string $bandwidth = '';

    /**
     * @var string ON or OFF. If ON, bandwidth is ignored and no limit is set
     */
    private string $ubandwidth = '';

    /**
     * @var string Amount of disk space Reseller will be allowed to use. Number, in Megabytes
     */
    private string $quota = '';

    /**
     * @var string ON or OFF. If ON, quota is ignored and no limit is set
     */
    private string $uquota = '';

    /**
     * @var string Number of domains the reseller and his/her User's are allowed to create
     */
    private string $vdomains = '';

    /**
     * @var string ON or OFF. If ON, vdomains is ignored and no limit is set
     */
    private string $uvdomains = '';

    /**
     * @var string Number of subdomains the reseller and his/her User's are allowed to create
     */
    private string $nsubdomains = '';

    /**
     * @var string ON or OFF. If ON, nsubdomains is ignored and no limit is set
     */
    private string $unsubdomains = '';

    /**
     * @var string Number of ips that will be allocated to the Reseller upon account during account
     */
    private string $ips = '';

    /**
     * @var string Number of pop accounts the reseller and his/her User's are allowed to create
     */
    private string $nemails = '';

    /**
     * @var string ON or OFF Unlimited option for nemails
     */
    private string $unemails = '';

    /**
     * @var string Number of forwarders the reseller and his/her User's are allowed to create
     */
    private string $nemailf = '';

    /**
     * @var string ON or OFF Unlimited option for nemailf
     */
    private string $unemailf = '';

    /**
     * @var string Number of mailing lists the reseller and his/her User's are allowed to create
     */
    private string $nemailml = '';

    /**
     * @var string ON or OFF Unlimited option for nemailml
     */
    private string $unemailml = '';

    /**
     * @var string Number of autoresponders the reseller and his/her User's are allowed to create
     */
    private string $nemailr = '';

    /**
     * @var string ON or OFF Unlimited option for nemailr
     */
    private string $unemailr = '';

    /**
     * @var string Number of MySQL databases the reseller and his/her User's are allowed to create
     */
    private string $mysql = '';

    /**
     * @var string ON or OFF Unlimited option for mysql
     */
    private string $umysql = '';

    /**
     * @var string Number of domain pointers the reseller and his/her User's are allowed to create
     */
    private string $domainptr = '';

    /**
     * @var string ON or OFF Unlimited option for domainptr
     */
    private string $udomainptr = '';

    /**
     * @var string Number of ftp accounts the reseller and his/her User's are allowed to create
     */
    private string $ftp = '';

    /**
     * @var string ON or OFF Unlimited option for ftp
     */
    private string $uftp = '';

    /**
     * @var string ON or OFF If ON, the reseller and his/her users will be able to have anonymous ftp accounts.
     */
    private string $aftp = '';

    /**
     * @var string ON or OFF If ON, the reseller and his/her users will have the ability to run php scripts.
     */
    private string $php = '';

    /**
     * @var string ON or OFF If ON, the reseller and his/her users will have the ability to run cgi scripts in their cgi-bins.
     */
    private string $cgi = '';

    /**
     * @var string ON or OFF If ON, the reseller and his/her users will have the ability to access their websites through secure https://.
     */
    private string $ssl = '';

    /**
     * @var string ON or OFF If ON, the reseller will be have an ssh account.
     */
    private string $ssh = '';

    /**
     * @var string ON or OFF If ON, the reseller will be allowed to create ssh accounts for his/her users.
     */
    private string $userssh = '';

    /**
     * @var string ON or OFF If ON, the reseller will be able to modify his/her dns records and to create users with or without this option.
     */
    private string $dnscontrol = '';

    /**
     * @var string OFF or TWO or THREE. If OFF, no dns's will be created. TWO: domain ip for ns1 and another ip for ns2. THREE: domain has own ip. ns1 and ns2 have their own ips
     */
    private string $dns = '';

    /**
     * @var string ON or OFF If ON, the reseller will have the ability to create users using the servers main ip.
     */
    private string $serverip = '';

    /**
     * @var string the max number of Users this Reseller can create. Can be set to nusers=unlimited too
     */
    private string $nusers = 'unlimited';

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
    public function setUsername(string $username): CreateReseller
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
    public function setEmail(string $email): CreateReseller
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
    public function setPasswd(string $passwd): CreateReseller
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
    public function setDomain(string $domain): CreateReseller
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
    public function setPackage(string $package): CreateReseller
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
     * @param string $ip 'shared' or 'assign'.
     *                   If shared, domain will use the server's main ip. assign will use one of the reseller's ips
     */
    public function setIp(string $ip): CreateReseller
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
    public function setNotify(string $notify): CreateReseller
    {
        $this->notify = $notify;

        return $this;
    }

    public function getBandwidth(): string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(string $bandwidth): CreateReseller
    {
        $this->bandwidth = $bandwidth;

        return $this;
    }

    public function getUbandwidth(): string
    {
        return $this->ubandwidth;
    }

    public function setUbandwidth(string $ubandwidth): CreateReseller
    {
        $this->ubandwidth = $ubandwidth;

        return $this;
    }

    public function getQuota(): string
    {
        return $this->quota;
    }

    public function setQuota(string $quota): CreateReseller
    {
        $this->quota = $quota;

        return $this;
    }

    public function getUquota(): string
    {
        return $this->uquota;
    }

    public function setUquota(string $uquota): CreateReseller
    {
        $this->uquota = $uquota;

        return $this;
    }

    public function getVdomains(): string
    {
        return $this->vdomains;
    }

    public function setVdomains(string $vdomains): CreateReseller
    {
        $this->vdomains = $vdomains;

        return $this;
    }

    public function getUvdomains(): string
    {
        return $this->uvdomains;
    }

    public function setUvdomains(string $uvdomains): CreateReseller
    {
        $this->uvdomains = $uvdomains;

        return $this;
    }

    public function getNsubdomains(): string
    {
        return $this->nsubdomains;
    }

    public function setNsubdomains(string $nsubdomains): CreateReseller
    {
        $this->nsubdomains = $nsubdomains;

        return $this;
    }

    public function getUnsubdomains(): string
    {
        return $this->unsubdomains;
    }

    public function setUnsubdomains(string $unsubdomains): CreateReseller
    {
        $this->unsubdomains = $unsubdomains;

        return $this;
    }

    public function getIps(): string
    {
        return $this->ips;
    }

    public function setIps(string $ips): CreateReseller
    {
        $this->ips = $ips;

        return $this;
    }

    public function getNemails(): string
    {
        return $this->nemails;
    }

    public function setNemails(string $nemails): CreateReseller
    {
        $this->nemails = $nemails;

        return $this;
    }

    public function getUnemails(): string
    {
        return $this->unemails;
    }

    public function setUnemails(string $unemails): CreateReseller
    {
        $this->unemails = $unemails;

        return $this;
    }

    public function getNemailf(): string
    {
        return $this->nemailf;
    }

    public function setNemailf(string $nemailf): CreateReseller
    {
        $this->nemailf = $nemailf;

        return $this;
    }

    public function getUnemailf(): string
    {
        return $this->unemailf;
    }

    public function setUnemailf(string $unemailf): CreateReseller
    {
        $this->unemailf = $unemailf;

        return $this;
    }

    public function getNemailml(): string
    {
        return $this->nemailml;
    }

    public function setNemailml(string $nemailml): CreateReseller
    {
        $this->nemailml = $nemailml;

        return $this;
    }

    public function getUnemailml(): string
    {
        return $this->unemailml;
    }

    public function setUnemailml(string $unemailml): CreateReseller
    {
        $this->unemailml = $unemailml;

        return $this;
    }

    public function getNemailr(): string
    {
        return $this->nemailr;
    }

    public function setNemailr(string $nemailr): CreateReseller
    {
        $this->nemailr = $nemailr;

        return $this;
    }

    public function getUnemailr(): string
    {
        return $this->unemailr;
    }

    public function setUnemailr(string $unemailr): CreateReseller
    {
        $this->unemailr = $unemailr;

        return $this;
    }

    public function getMysql(): string
    {
        return $this->mysql;
    }

    public function setMysql(string $mysql): CreateReseller
    {
        $this->mysql = $mysql;

        return $this;
    }

    public function getUmysql(): string
    {
        return $this->umysql;
    }

    public function setUmysql(string $umysql): CreateReseller
    {
        $this->umysql = $umysql;

        return $this;
    }

    public function getDomainptr(): string
    {
        return $this->domainptr;
    }

    public function setDomainptr(string $domainptr): CreateReseller
    {
        $this->domainptr = $domainptr;

        return $this;
    }

    public function getUdomainptr(): string
    {
        return $this->udomainptr;
    }

    public function setUdomainptr(string $udomainptr): CreateReseller
    {
        $this->udomainptr = $udomainptr;

        return $this;
    }

    public function getFtp(): string
    {
        return $this->ftp;
    }

    public function setFtp(string $ftp): CreateReseller
    {
        $this->ftp = $ftp;

        return $this;
    }

    public function getUftp(): string
    {
        return $this->uftp;
    }

    public function setUftp(string $uftp): CreateReseller
    {
        $this->uftp = $uftp;

        return $this;
    }

    public function getAftp(): string
    {
        return $this->aftp;
    }

    public function setAftp(string $aftp): CreateReseller
    {
        $this->aftp = $aftp;

        return $this;
    }

    public function getPhp(): string
    {
        return $this->php;
    }

    public function setPhp(string $php): CreateReseller
    {
        $this->php = $php;

        return $this;
    }

    public function getCgi(): string
    {
        return $this->cgi;
    }

    public function setCgi(string $cgi): CreateReseller
    {
        $this->cgi = $cgi;

        return $this;
    }

    public function getSsl(): string
    {
        return $this->ssl;
    }

    public function setSsl(string $ssl): CreateReseller
    {
        $this->ssl = $ssl;

        return $this;
    }

    public function getSsh(): string
    {
        return $this->ssh;
    }

    public function setSsh(string $ssh): CreateReseller
    {
        $this->ssh = $ssh;

        return $this;
    }

    public function getUserssh(): string
    {
        return $this->userssh;
    }

    public function setUserssh(string $userssh): CreateReseller
    {
        $this->userssh = $userssh;

        return $this;
    }

    public function getDnscontrol(): string
    {
        return $this->dnscontrol;
    }

    public function setDnscontrol(string $dnscontrol): CreateReseller
    {
        $this->dnscontrol = $dnscontrol;

        return $this;
    }

    public function getDns(): string
    {
        return $this->dns;
    }

    /**
     * @param string $dns OFF or TWO or THREE.
     *                    If OFF, no dns's will be created.
     *                    TWO: domain ip for ns1 and another ip for ns2.
     *                    THREE: domain has own ip. ns1 and ns2 have their own ips
     */
    public function setDns(string $dns): CreateReseller
    {
        $this->dns = $dns;

        return $this;
    }

    public function getServerip(): string
    {
        return $this->serverip;
    }

    /**
     * @param string $serverip ON or OFF
     *                         If ON, the reseller will have the ability to create users using the servers main ip.
     */
    public function setServerip(string $serverip): CreateReseller
    {
        $this->serverip = $serverip;

        return $this;
    }

    public function setNusers(string $nusers): CreateReseller
    {
        $this->nusers = $nusers;

        return $this;
    }

    public function getNusers(): string
    {
        return $this->nusers;
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
            'ssl' => $this->getSsl(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
