<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Packages;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ManageUserPackages extends DirectAdminCommand
{
    /**
     * More information: https://www.directadmin.com/features.php?id=1298.
     *
     * @var string API Command
     */
    protected string $command = 'CMD_API_MANAGE_USER_PACKAGES';

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
     * @var string OFF or ON, Allowed to use their dns panel
     */
    private string $dnscontrol = 'OFF';

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
     * @var string unlimited or number in megabytes
     */
    private string $quota = '100';

    /**
     * @var string Skin to be assigned
     */
    private string $skin = 'enhanced';

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
     * @var string OFF or ON, To determine if the users account will be suspended when the bandwidth is used up
     */
    private string $suspend_at_limit = 'ON';

    /**
     * @var string name of the new package.
     */
    private $packagename;

    /**
     * @var string Language for the package.
     */
    private string $language = 'en';

    /**
     * @var string ON or OFF, To determine if the users account can have access to system info
     */
    private string $sysinfo = 'ON';

    /**
     * @var int unlimited or number of amount of IP's
     */
    private int $ips = 0;

    /**
     * @var string OFF or ON, Allowed to use the server ip for his users
     */
    private string $serverip = 'ON';

    /**
     * @var string OFF or ON, Allowed to give out ssh access to his users
     */
    private string $userssh = 'OFF';

    /**
     * @var string ON or OFF If ON, the User will have the ability to enable and customize a catch-all email
     */
    private string $catchall = 'ON';

    public function __construct()
    {
        $this->packagename = (string) time();
    }

    public function getName(): string
    {
        return empty($this->name) ? '' : $this->name;
    }

    public function getCron(): string
    {
        return $this->cron;
    }

    public function setCron(string $cron): ManageUserPackages
    {
        $this->cron = $cron;
        return $this;
    }

    public function getSysinfo(): string
    {
        return $this->sysinfo;
    }

    public function setSysinfo(string $sysinfo): ManageUserPackages
    {
        $this->sysinfo = $sysinfo;
        return $this;
    }

    public function getSkin(): string
    {
        return $this->skin;
    }

    public function setSkin(string $skin): ManageUserPackages
    {
        $this->skin = $skin;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): ManageUserPackages
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Return the User package properties as array.
     *
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'aftp' => $this->getAftp(),
            'cgi' => $this->getCgi(),
            'dns' => $this->getDnscontrol(),
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
            'catchall' => $this->getCatchall(),
            'suspend_at_limit' => $this->getSuspendAtLimit(),
            'skin' => $this->getSkin(),
            'cron' => $this->getCron(),
            'language' => $this->getLanguage(),
            'sysinfo' => $this->getSysinfo(),
        ];
    }

    public function getAftp(): string
    {
        return $this->aftp;
    }

    public function setAftp(string $aftp): ManageUserPackages
    {
        $this->aftp = $aftp;
        return $this;
    }

    public function getCgi(): string
    {
        return $this->cgi;
    }

    public function setCgi(string $cgi): ManageUserPackages
    {
        $this->cgi = $cgi;
        return $this;
    }

    public function getDnscontrol(): string
    {
        return $this->dnscontrol;
    }

    public function setDnscontrol(string $dnscontrol): ManageUserPackages
    {
        $this->dnscontrol = $dnscontrol;
        return $this;
    }

    public function getBandwidth(): string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(string $bandwidth): ManageUserPackages
    {
        $this->bandwidth = $bandwidth;
        return $this;
    }

    public function getDomainptr(): string
    {
        return $this->domainptr;
    }

    public function setDomainptr(string $domainptr): ManageUserPackages
    {
        $this->domainptr = $domainptr;
        return $this;
    }

    public function getFtp(): string
    {
        return $this->ftp;
    }

    public function setFtp(string $ftp): ManageUserPackages
    {
        $this->ftp = $ftp;
        return $this;
    }

    public function getIps(): int
    {
        return $this->ips;
    }

    public function setIps(int $ips): ManageUserPackages
    {
        $this->ips = $ips;
        return $this;
    }

    public function getMysql(): string
    {
        return $this->mysql;
    }

    public function setMysql(string $mysql): ManageUserPackages
    {
        $this->mysql = $mysql;
        return $this;
    }

    public function getNemailf(): string
    {
        return $this->nemailf;
    }

    public function setNemailf(string $nemailf): ManageUserPackages
    {
        $this->nemailf = $nemailf;
        return $this;
    }

    public function getNemailml(): string
    {
        return $this->nemailml;
    }

    public function setNemailml(string $nemailml): ManageUserPackages
    {
        $this->nemailml = $nemailml;
        return $this;
    }

    public function getNemailr(): string
    {
        return $this->nemailr;
    }

    public function setNemailr(string $nemailr): ManageUserPackages
    {
        $this->nemailr = $nemailr;
        return $this;
    }

    public function getNemails(): string
    {
        return $this->nemails;
    }

    public function setNemails(string $nemails): ManageUserPackages
    {
        $this->nemails = $nemails;
        return $this;
    }

    public function getNsubdomains(): string
    {
        return $this->nsubdomains;
    }

    public function setNsubdomains(string $nsubdomains): ManageUserPackages
    {
        $this->nsubdomains = $nsubdomains;
        return $this;
    }

    public function getQuota(): string
    {
        return $this->quota;
    }

    public function setQuota(string $quota): ManageUserPackages
    {
        $this->quota = $quota;
        return $this;
    }

    public function getServerip(): string
    {
        return $this->serverip;
    }

    public function setServerip(string $serverip): ManageUserPackages
    {
        $this->serverip = $serverip;
        return $this;
    }

    public function getSsh(): string
    {
        return $this->ssh;
    }

    public function setSsh(string $ssh): ManageUserPackages
    {
        $this->ssh = $ssh;
        return $this;
    }

    public function getUserssh(): string
    {
        return $this->userssh;
    }

    public function setUserssh(string $userssh): ManageUserPackages
    {
        $this->userssh = $userssh;
        return $this;
    }

    public function getSsl(): string
    {
        return $this->ssl;
    }

    public function setSsl(string $ssl): ManageUserPackages
    {
        $this->ssl = $ssl;
        return $this;
    }

    public function getVdomains(): string
    {
        return $this->vdomains;
    }

    public function setVdomains(string $vdomains): ManageUserPackages
    {
        $this->vdomains = $vdomains;
        return $this;
    }

    public function getPackagename(): string
    {
        return $this->packagename;
    }

    public function setPackagename(string $packagename): ManageUserPackages
    {
        $this->packagename = $packagename;
        return $this;
    }

    public function getPhp(): string
    {
        return $this->php;
    }

    public function setPhp(string $php): ManageUserPackages
    {
        $this->php = $php;
        return $this;
    }

    public function getSpam(): string
    {
        return $this->spam;
    }

    public function setSpam(string $spam): ManageUserPackages
    {
        $this->spam = $spam;
        return $this;
    }

    public function getCatchall(): string
    {
        return $this->catchall;
    }

    public function setCatchall(string $catchall): ManageUserPackages
    {
        $this->catchall = $catchall;
        return $this;
    }

    public function getSuspendAtLimit(): string
    {
        return $this->suspend_at_limit;
    }

    public function setSuspendAtLimit(string $suspend_at_limit): ManageUserPackages
    {
        $this->suspend_at_limit = $suspend_at_limit;
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
