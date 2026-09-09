<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ModifyUser extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_MODIFY_USER';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    /**
     * @var bool set the response to be json
     */
    protected bool $useJsonResponse = true;

    /**
     * @var array<mixed, mixed> containing data to be modified for this user.
     */
    private array $userData = [
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

        if (array_key_exists('success', $decodedContent) && $decodedContent['success'] === 'User Modified') {
            $this->result = $decodedContent['success'];
            $this->succeeded = true;
        }

        return $this;
    }

    /**
     * Setter for all user data. This will override any existing set data. The 'action'
     * gets overwritten to da's 'customize' to keep the integrity of this command.
     *
     * @param array<mixed, mixed> $userData complete array of all user data to set
     */
    public function setUserData(array $userData): void
    {
        $userData['action'] = 'customize';
        $this->userData = $userData;
    }

    /**
     * @param string $bandwidth Amount of bandwidth User will be allowed to use. Number, in Megabytes
     */
    public function setBandwidth(string $bandwidth): ModifyUser
    {
        $this->userData['bandwidth'] = $bandwidth;
        return $this;
    }

    /**
     * @param string $ubandwidth ON or OFF. If ON, bandwidth is ignored and no limit is set
     */
    public function setUbandwidth(string $ubandwidth): ModifyUser
    {
        $this->userData['ubandwidth'] = $ubandwidth;
        return $this;
    }

    /**
     * @param string $quota Amount of disk space User will be allowed to use. Number, in Megabytes
     */
    public function setQuota(string $quota): ModifyUser
    {
        $this->userData['quota'] = $quota;
        return $this;
    }

    /**
     * @param string $uquota ON or OFF. If ON, quota is ignored and no limit is set
     */
    public function setUquota(string $uquota): ModifyUser
    {
        $this->userData['uquota'] = $uquota;
        return $this;
    }

    /**
     * @param string $vdomains Number of domains the User will be allowed to create
     */
    public function setVdomains(string $vdomains): ModifyUser
    {
        $this->userData['vdomains'] = $vdomains;
        return $this;
    }

    /**
     * @param string $uvdomains ON or OFF. If ON, vdomains is ignored and no limit is set
     */
    public function setUvdomains(string $uvdomains): ModifyUser
    {
        $this->userData['uvdomains'] = $uvdomains;
        return $this;
    }

    /**
     * @param string $nsubdomains Number of subdomains the User will be allowed to create
     */
    public function setNsubdomains(string $nsubdomains): ModifyUser
    {
        $this->userData['nsubdomains'] = $nsubdomains;
        return $this;
    }

    /**
     * @param string $unsubdomains ON or OFF. If ON, nsubdomains is ignored and no limit is set
     */
    public function setUnsubdomains(string $unsubdomains): ModifyUser
    {
        $this->userData['unsubdomains'] = $unsubdomains;
        return $this;
    }

    /**
     * @param string $nemails Number of pop accounts the User will be allowed to create
     */
    public function setNemails(string $nemails): ModifyUser
    {
        $this->userData['nemails'] = $nemails;
        return $this;
    }

    /**
     * @param string $unemails ON or OFF Unlimited option for nemails
     */
    public function setUnemails(string $unemails): ModifyUser
    {
        $this->userData['unemails'] = $unemails;
        return $this;
    }

    /**
     * @param string $nemailf Number of forwarders the User will be allowed to create
     */
    public function setNemailf(string $nemailf): ModifyUser
    {
        $this->userData['nemailf'] = $nemailf;
        return $this;
    }

    /**
     * @param string $unemailf ON or OFF Unlimited option for nemailf
     */
    public function setUnemailf(string $unemailf): ModifyUser
    {
        $this->userData['unemailf'] = $unemailf;
        return $this;
    }

    /**
     * @param string $nemailml Number of mailing lists the User will be allowed to create
     */
    public function setNemailml(string $nemailml): ModifyUser
    {
        $this->userData['nemailml'] = $nemailml;
        return $this;
    }

    /**
     * @param string $unemailml ON or OFF Unlimited option for nemailml
     */
    public function setUnemailml(string $unemailml): ModifyUser
    {
        $this->userData['unemailml'] = $unemailml;
        return $this;
    }

    /**
     * @param string $nemailr Number of autoresponders the User will be allowed to create
     */
    public function setNemailr(string $nemailr): ModifyUser
    {
        $this->userData['nemailr'] = $nemailr;
        return $this;
    }

    /**
     * @param string $unemailr ON or OFF Unlimited option for nemailr
     */
    public function setUnemailr(string $unemailr): ModifyUser
    {
        $this->userData['unemailr'] = $unemailr;
        return $this;
    }

    /**
     * @param string $mysql Number of MySQL databases the User will be allowed to create
     */
    public function setMysql(string $mysql): ModifyUser
    {
        $this->userData['mysql'] = $mysql;
        return $this;
    }

    /**
     * @param string $umysql ON or OFF Unlimited option for mysql
     */
    public function setUmysql(string $umysql): ModifyUser
    {
        $this->userData['umysql'] = $umysql;
        return $this;
    }

    /**
     * @param string $domainptr Number of domain pointers the User will be allowed to create
     */
    public function setDomainptr(string $domainptr): ModifyUser
    {
        $this->userData['domainptr'] = $domainptr;
        return $this;
    }

    /**
     * @param string $udomainptr ON or OFF Unlimited option for domainptr
     */
    public function setUdomainptr(string $udomainptr): ModifyUser
    {
        $this->userData['udomainptr'] = $udomainptr;
        return $this;
    }

    /**
     * @param string $loginKeys ON or OFF to allow the user to have or create Login Keys.
     */
    public function setLoginKeys(string $loginKeys): ModifyUser
    {
        $this->userData['login_keys'] = $loginKeys;
        return $this;
    }

    /**
     * @param string $ftp Number of ftp accounts the User will be allowed to create
     */
    public function setFtp(string $ftp): ModifyUser
    {
        $this->userData['ftp'] = $ftp;
        return $this;
    }

    /**
     * @param string $uftp ON or OFF Unlimited option for ftp
     */
    public function setUftp(string $uftp): ModifyUser
    {
        $this->userData['uftp'] = $uftp;
        return $this;
    }

    /**
     * @param string $aftp ON or OFF If ON, the User will be able to have anonymous ftp accounts.
     */
    public function setAftp(string $aftp): ModifyUser
    {
        $this->userData['aftp'] = $aftp;
        return $this;
    }

    /**
     * @param string $cgi ON or OFF If ON, the User will have the ability to run cgi scripts in their cgi-bin.
     */
    public function setCgi(string $cgi): ModifyUser
    {
        $this->userData['cgi'] = $cgi;
        return $this;
    }

    /**
     * @param string $php ON or OFF If ON, the User will have the ability to run php scripts.
     */
    public function setPhp(string $php): ModifyUser
    {
        $this->userData['php'] = $php;
        return $this;
    }

    /**
     * @param string $spam ON or OFF If ON, the User will have the ability to turn on spamassassin.
     */
    public function setSpam(string $spam): ModifyUser
    {
        $this->userData['spam'] = $spam;
        return $this;
    }

    /**
     * @param string $cron ON or OFF If ON, the User will have the ability to run cron jobs
     */
    public function setCron(string $cron): ModifyUser
    {
        $this->userData['cron'] = $cron;
        return $this;
    }

    /**
     * @param string $ssl ON or OFF If ON, the User will have the ability to access their websites through secure https://.
     */
    public function setSsl(string $ssl): ModifyUser
    {
        $this->userData['ssl'] = $ssl;
        return $this;
    }

    /**
     * @param string $sysinfo ON or OFF If ON, the User will have the ability to view the system information page.
     */
    public function setSysinfo(string $sysinfo): ModifyUser
    {
        $this->userData['sysinfo'] = $sysinfo;
        return $this;
    }

    /**
     * @param string $ssh ON or OFF If ON, the User will have an ssh account.
     */
    public function setSsh(string $ssh): ModifyUser
    {
        $this->userData['ssh'] = $ssh;
        return $this;
    }

    /**
     * @param string $dnscontrol ON or OFF If ON, the User will be able to modify his/her dns records.
     */
    public function setDnscontrol(string $dnscontrol): ModifyUser
    {
        $this->userData['dnscontrol'] = $dnscontrol;
        return $this;
    }

    /**
     * @param string $skin the Resellers list of skins. If the skin doesn't exist or is invalid, it will not be changed
     */
    public function setSkin(string $skin): ModifyUser
    {
        $this->userData['skin'] = $skin;
        return $this;
    }

    /**
     * @param string $ns1 Name server 1 that the user will use. Will remove the old one and replace it with this one
     */
    public function setNs1(string $ns1): ModifyUser
    {
        $this->userData['ns1'] = $ns1;
        return $this;
    }

    /**
     * @param string $ns2 Name server 2 that the user will use. Will remove the old one and replace it with this one
     */
    public function setNs2(string $ns2): ModifyUser
    {
        $this->userData['ns2'] = $ns2;
        return $this;
    }

    /**
     * @param string $user the username of the User to be modified
     */
    public function setUser(string $user): ModifyUser
    {
        $this->userData['user'] = $user;
        return $this;
    }

    /**
     * @param string $package the package name of the package to be modified
     */
    public function setPackage(string $package): ModifyUser
    {
        $this->userData['package'] = $package;
        $this->setAction('package');

        return $this;
    }

    /**
     * @param string $catch the username of the User to be modified
     */
    public function setCatchall(string $catch): ModifyUser
    {
        $this->userData['catchall'] = $catch;
        return $this;
    }

    /**
     * @param string $featureSets semicolon separated feature sets to limit this user by (empty string means allow all)
     */
    public function setFeatureSets(string $featureSets): ModifyUser
    {
        $this->userData['feature_sets'] = $featureSets;
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
     * @param string $action the action of this command.
     */
    private function setAction(string $action): void
    {
        $this->userData['action'] = $action;
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = $this->userData;

        return Utils::streamFor(http_build_query($params));
    }
}
