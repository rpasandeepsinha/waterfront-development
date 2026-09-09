<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands;

class AdminStats extends DirectAdminCommand
{
    /**
     * @var string Command as documented in DirectAdmin API
     */
    protected string $command = 'CMD_API_ADMIN_STATS';

    /**
     * @var string Method used to call API
     */
    protected string $method = 'GET';

    /**
     * Number of megabytes currently received through the adaptor
     * with units, as returned by 'ifconfig'. eg. 1045.4 Mb.
     *
     * @var string Number of megabytes received
     */
    protected string $rx;

    /**
     * Number of megabytes currently sent through the adaptor
     * with units, as returned by 'ifconfig'. eg. 5345.4 Mb.
     *
     * @var string Number of megabytes sent
     */
    protected string $tx;

    /**
     * @var string Number of megabytes sent and recorded by DirectAdmin
     */
    protected string $bandwidth;

    /**
     * @var string Number of megabytes of disk space used by users as recorded by DirectAdmin
     */
    protected string $quota;

    /**
     * Disk Usage as returned by 'df'. Stored in a colon separated list.
     * eg: /dev/hda6:381139:276138:85323:77%:/ Where data is: Filesystem:1k-blocks:Used:Available:Use%:Mounted on.
     *
     * @var mixed[] Disk Usage as returned by 'df'.
     */
    protected array $disk;

    /**
     * @var int|string Number of domain pointers on the server or 'unlimited'
     */
    protected int|string $domainptr;

    /**
     * @var int|string Number of ftp accounts on the server or 'unlimited'
     */
    protected int|string $ftp;

    /**
     * @var int|string Number of databases on the server or 'unlimited'
     */
    protected int|string $mysql;

    /**
     * @var int|string Number of email forwarders on the server or 'unlimited'
     */
    protected int|string $nemailf;

    /**
     * @var int|string Number of mailing lists on the server or unlimited
     */
    protected int|string $nemailml;

    /**
     * @var int|string Number of autoresponders on the server or unlimited
     */
    protected int|string $nemailr;

    /**
     * @var int|string Number of pop accounts on the server or unlimited
     */
    protected int|string $nemails;

    /**
     * @var int|string Number of resellers on the server or unlimited
     */
    protected int|string $nresellers;

    /**
     * @var int|string Number of subdomains on the server or unlimited
     */
    protected int|string $nsubdomains;

    /**
     * @var int|string Number of users on the server or unlimited
     */
    protected int|string $nusers;

    /**
     * @var int|string Number of virtual domains on the server or unlimited
     */
    protected int|string $vdomains;

    /**
     * Number of megabytes currently received through the adaptor
     * with units, as returned by 'ifconfig'. eg. 1045.4 Mb.
     *
     * @return string Number of megabytes received
     */
    public function getRx(): string
    {
        return $this->rx;
    }

    /**
     * Number of megabytes currently sent through the adaptor
     * with units, as returned by 'ifconfig'. eg. 5345.4 Mb.
     *
     * @return string Number of megabytes sent
     */
    public function getTx(): string
    {
        return $this->tx;
    }

    /**
     * Get number of megabytes sent and recorded by DirectAdmin.
     *
     * @return string Number of megabytes sent and recorded by DirectAdmin
     */
    public function getBandwidth(): string
    {
        return $this->bandwidth;
    }

    /**
     * Get Number of megabytes of disk space used by users as recorded by DirectAdmin.
     *
     * @return string Number of megabytes of disk space used by users as recorded by DirectAdmin
     */
    public function getQuota(): string
    {
        return $this->quota;
    }

    /**
     * Get Disk Usage as returned by 'df'. Stored in a colon separated list.
     * eg: /dev/hda6:381139:276138:85323:77%:/ Where data is: Filesystem:1k-blocks:Used:Available:Use%:Mounted on.
     *
     * @return mixed[] Disk Usage as returned by 'df'.
     */
    public function getDisk(): array
    {
        return $this->disk;
    }

    /**
     * Get Number of domain pointers on the server.
     *
     * @return int|string Number of domain pointers on the server or 'unlimited'
     */
    public function getDomainPtr(): int|string
    {
        return $this->domainptr;
    }

    /**
     * Get Number of ftp accounts on the server.
     *
     * @return int|string Number of ftp accounts or 'unlimited'
     */
    public function getFtp(): int|string
    {
        return $this->ftp;
    }

    /**
     * Get Number of databases on the server.
     *
     * @return int|string Number of databases on the server or unlimited
     */
    public function getMysql(): int|string
    {
        return $this->mysql;
    }

    /**
     * Get Number of email forwarders on the server.
     *
     * @return int|string Number of email forwarders on the server or unlimited
     */
    public function getNemailf(): int|string
    {
        return $this->nemailf;
    }

    /**
     * Get Number of mailing lists on the server.
     *
     * @return int|string Number of mailing lists on the server or unlimited
     */
    public function getNemailml(): int|string
    {
        return $this->nemailml;
    }

    /**
     * Get Number of autoresponders on the server.
     *
     * @return int|string Number of autoresponders on the server or unlimited
     */
    public function getNemailr(): int|string
    {
        return $this->nemailr;
    }

    /**
     * Get Number of pop accounts on the server.
     *
     * @return int|string Number of pop accounts on the server or unlimited
     */
    public function getNemails(): int|string
    {
        return $this->nemails;
    }

    /**
     * Get Number of resellers on the server.
     *
     * @return int|string Number of resellers on the server or unlimited
     */
    public function getNresellers(): int|string
    {
        return $this->nresellers;
    }

    /**
     * Get Number of subdomains on the server.
     *
     * @return int|string Number of subdomains on the server or unlimited
     */
    public function getNsubdomains(): int|string
    {
        return $this->nsubdomains;
    }

    /**
     * Get Number of users on the server.
     *
     * @return int|string Number of users on the server or unlimited
     */
    public function getNusers(): int|string
    {
        return $this->nusers;
    }

    /**
     * Get Number of virtual domains on the server or 'unlimited'.
     */
    public function getVdomains(): int|string
    {
        return $this->vdomains;
    }
}
