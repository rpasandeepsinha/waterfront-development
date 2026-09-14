<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Packages;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ManageResellerPackages extends DirectAdminCommand
{
    /**
     * More information: https://www.directadmin.com/features.php?id=1298.
     *
     * @var string API Command
     */
    protected string $command = 'CMD_API_MANAGE_RESELLER_PACKAGES';

    protected string $method = 'POST';

    /**
     * @var string OFF or ON, Allowed to use anonymous ftp
     */
    private string $aftp = 'OFF';

    /**
     * @var string OFF or ON, Allowed to use cgi-bin's
     */
    private string $cgi = 'OFF';

    /**
     * @var string inode
     */
    private string $inode = '10';

    /**
     * @var string ON or OFF. If ON, inode is ignored and no limit is set
     */
    private string $uinode = 'ON';

    /**
     * @var string OFF or ON, Allowed to use their dns panel
     */
    private string $dnscontrol = 'OFF';

    /**
     * @var string If OFF, no dns's will be created. TWO: domain ip for ns1 and another ip for ns2. THREE: domain has own ip. ns1 and ns2 have their own ips
     */
    private string $dns = 'OFF';

    /**
     * @var string unlimited or number in megabytes
     */
    private string $bandwidth = '1000';

    /**
     * @var string unlimited or quantity
     */
    private string $domainptr = '1';

    /**
     * @var string unlimited or quantity
     */
    private string $ftp = '1';

    /**
     * @var string unlimited or quantity
     */
    private string $mysql = '5';

    /**
     * Email Forwarders.
     *
     * @var string unlimited or quantity
     */
    private string $nemailf = '0';

    /**
     * Mailing Lists.
     *
     * @var string unlimited or quantity
     */
    private string $nemailml = '0';

    /**
     * Number of users.
     *
     * @var string the max number of Users this Reseller can create. Can be set to nusers=unlimited too
     */
    private string $nusers = 'unlimited';

    /**
     * AutoResponders.
     *
     * @var string unlimited or quantity
     */
    private string $nemailr = '0';

    /**
     * @var string unlimited or quantity
     */
    private string $nemails = '10';

    /**
     * @var string unlimited or quantity
     */
    private string $nsubdomains = '10';

    /**
     * @var string ON or OFF If ON, the reseller will be allowed to create ssh accounts for his/her users.
     */
    private string $userssh = 'ON';

    /**
     * @var string unlimited or number in megabytes
     */
    private string $quota = '100';

    /**
     * @var string OFF or ON, Allowed to have ssh access
     */
    private string $ssh = 'OFF';

    /**
     * @var string OFF or ON, Allowed to give out https ssl
     */
    private string $ssl = 'ON';

    /**
     * @var string OFF or ON, Allowed access to PHP
     */
    private string $php = 'ON';

    /**
     * @var string OFF or ON, Allowed access to Cron Jobs
     */
    private string $cron = 'ON';

    /**
     * @var string OFF or ON, Allowed access to SpamAssassin
     */
    private string $spam = 'ON';

    /**
     * @var string unlimited or quantity of domains
     */
    private string $vdomains = '1';

    /**
     * @var string name of the new package.
     */
    private string $packagename;

    /**
     * @var string name of the old package to be renamed.
     */
    private string $old_packagename = '';

    /**
     * @var string ON or OFF, To determine if the users account can have access to system info
     */
    private string $sysinfo = 'ON';

    /**
     * @var string unlimited or number of amount of IP's
     */
    private string $ips = '0';

    /**
     * @var string ON or OFF If ON, the reseller will have the ability to create users using the servers main ip.
     */
    private string $serverip = 'ON';

    /**
     * @var string ON or OFF If ON, the User will have the ability to enable and customize a catch-all email
     */
    private string $catchall = 'ON';

    /**
     * @var string Allow Overselling
     */
    private string $oversell = 'ON';

    /**
     * @var string ON or OFF If ON, the Reseller will have access to the Login Key system for extra account passwords.
     */
    private string $login_keys = 'ON';

    public function __construct()
    {
        $this->packagename = strval(time());
    }

    public function getCron(): string
    {
        return $this->cron;
    }

    public function setCron(string $cron): ManageResellerPackages
    {
        $this->cron = $cron;

        return $this;
    }

    public function getSysinfo(): string
    {
        return $this->sysinfo;
    }

    public function setSysinfo(string $sysinfo): ManageResellerPackages
    {
        $this->sysinfo = $sysinfo;

        return $this;
    }

    /**
     * Return the User package properties as array.
     *
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'aftp' => $this->getAftp(),
            'cgi' => $this->getCgi(),
            'dns' => $this->getDns(),
            'dnscontrol' => $this->getDnscontrol(),
            'bandwidth' => $this->getBandwidth(),
            'domainptr' => $this->getDomainptr(),
            'ftp' => $this->getFtp(),
            'ips' => $this->getIps(),
            'mysql' => $this->getMysql(),
            'nemailf' => $this->getNemailf(),
            'nemailml' => $this->getNemailml(),
            'nemailr' => $this->getNemailr(),
            'nemails' => $this->getNemails(),
            'nsubdomains' => $this->getNsubdomains(),
            'quota' => $this->getQuota(),
            'serverip' => $this->getServerip(),
            'ssh' => $this->getSsh(),
            'userssh' => $this->getUserssh(),
            'ssl' => $this->getSsl(),
            'vdomains' => $this->getVdomains(),
            'packagename' => $this->getPackagename(),
            'php' => $this->getPhp(),
            'spam' => $this->getSpam(),
            'catchall' => $this->getCatchAll(),
            'cron' => $this->getCron(),
            'sysinfo' => $this->getSysinfo(),
            'login_keys' => $this->getLoginKeys(),
            'oversell' => $this->getOversell(),
            'old_packagename' => $this->getOldPackagename(),
            'nusers' => $this->getNusers(),
        ];
    }

    public function getAftp(): string
    {
        return $this->aftp;
    }

    public function setAftp(string $aftp): ManageResellerPackages
    {
        $this->aftp = $aftp;

        return $this;
    }

    public function getCgi(): string
    {
        return $this->cgi;
    }

    public function setCgi(string $cgi): ManageResellerPackages
    {
        $this->cgi = $cgi;

        return $this;
    }

    public function getDnscontrol(): string
    {
        return $this->dnscontrol;
    }

    public function setDnscontrol(string $dnscontrol): ManageResellerPackages
    {
        $this->dnscontrol = $dnscontrol;

        return $this;
    }

    public function getBandwidth(): string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(string $bandwidth): ManageResellerPackages
    {
        $this->bandwidth = $bandwidth;

        return $this;
    }

    public function getDomainptr(): string
    {
        return $this->domainptr;
    }

    public function setDomainptr(string $domainptr): ManageResellerPackages
    {
        $this->domainptr = $domainptr;

        return $this;
    }

    public function getFtp(): string
    {
        return $this->ftp;
    }

    public function setFtp(string $ftp): ManageResellerPackages
    {
        $this->ftp = $ftp;

        return $this;
    }

    public function getIps(): string
    {
        return $this->ips;
    }

    public function setIps(string $ips): ManageResellerPackages
    {
        $this->ips = $ips;

        return $this;
    }

    public function getMysql(): string
    {
        return $this->mysql;
    }

    public function setMysql(string $mysql): ManageResellerPackages
    {
        $this->mysql = $mysql;

        return $this;
    }

    public function getNemailf(): string
    {
        return $this->nemailf;
    }

    public function setNemailf(string $nemailf): ManageResellerPackages
    {
        $this->nemailf = $nemailf;

        return $this;
    }

    public function getNemailml(): string
    {
        return $this->nemailml;
    }

    public function setNemailml(string $nemailml): ManageResellerPackages
    {
        $this->nemailml = $nemailml;

        return $this;
    }

    public function getNemailr(): string
    {
        return $this->nemailr;
    }

    public function setNemailr(string $nemailr): ManageResellerPackages
    {
        $this->nemailr = $nemailr;

        return $this;
    }

    public function getNemails(): string
    {
        return $this->nemails;
    }

    public function setNemails(string $nemails): ManageResellerPackages
    {
        $this->nemails = $nemails;

        return $this;
    }

    public function getNsubdomains(): string
    {
        return $this->nsubdomains;
    }

    public function setNsubdomains(string $nsubdomains): ManageResellerPackages
    {
        $this->nsubdomains = $nsubdomains;

        return $this;
    }

    public function getQuota(): string
    {
        return $this->quota;
    }

    public function setQuota(string $quota): ManageResellerPackages
    {
        $this->quota = $quota;

        return $this;
    }

    public function getServerip(): string
    {
        return $this->serverip;
    }

    public function setServerip(string $serverip): ManageResellerPackages
    {
        $this->serverip = $serverip;

        return $this;
    }

    public function getSsh(): string
    {
        return $this->ssh;
    }

    public function setSsh(string $ssh): ManageResellerPackages
    {
        $this->ssh = $ssh;

        return $this;
    }

    public function getUserssh(): string
    {
        return $this->userssh;
    }

    public function setUserssh(string $userssh): ManageResellerPackages
    {
        $this->userssh = $userssh;

        return $this;
    }

    public function getSsl(): string
    {
        return $this->ssl;
    }

    public function setSsl(string $ssl): ManageResellerPackages
    {
        $this->ssl = $ssl;

        return $this;
    }

    public function getVdomains(): string
    {
        return $this->vdomains;
    }

    public function setVdomains(string $vdomains): ManageResellerPackages
    {
        $this->vdomains = $vdomains;

        return $this;
    }

    public function getPackagename(): string
    {
        return $this->packagename;
    }

    public function setPackagename(string $packagename): ManageResellerPackages
    {
        $this->packagename = $packagename;

        return $this;
    }

    public function getPhp(): string
    {
        return $this->php;
    }

    public function setPhp(string $php): ManageResellerPackages
    {
        $this->php = $php;

        return $this;
    }

    public function getSpam(): string
    {
        return $this->spam;
    }

    public function setSpam(string $spam): ManageResellerPackages
    {
        $this->spam = $spam;

        return $this;
    }

    public function getCatchAll(): string
    {
        return $this->catchall;
    }

    public function setCatchall(string $catchall): ManageResellerPackages
    {
        $this->catchall = $catchall;

        return $this;
    }

    public function getInode(): string
    {
        return $this->inode;
    }

    public function setInode(string $inode): ManageResellerPackages
    {
        $this->inode = $inode;

        return $this;
    }

    public function getUinode(): string
    {
        return $this->uinode;
    }

    public function setUinode(string $uinode): ManageResellerPackages
    {
        $this->uinode = $uinode;

        return $this;
    }

    public function getDns(): string
    {
        return $this->dns;
    }

    public function setDns(string $dns): ManageResellerPackages
    {
        $this->dns = $dns;

        return $this;
    }

    public function getNusers(): string
    {
        return $this->nusers;
    }

    public function setNusers(string $nusers): ManageResellerPackages
    {
        $this->nusers = $nusers;

        return $this;
    }

    public function getOldPackagename(): string
    {
        return $this->old_packagename;
    }

    public function setOldPackagename(string $old_packagename): ManageResellerPackages
    {
        $this->old_packagename = $old_packagename;

        return $this;
    }

    public function getOversell(): string
    {
        return $this->oversell;
    }

    public function setOversell(string $oversell): ManageResellerPackages
    {
        $this->oversell = $oversell;

        return $this;
    }

    public function getLoginKeys(): string
    {
        return $this->login_keys;
    }

    public function setLoginKeys(string $login_keys): ManageResellerPackages
    {
        $this->login_keys = $login_keys;

        return $this;
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = $this->toArray();
        $params['add'] = 'save';

        return Utils::streamFor(http_build_query($params));
    }
}
