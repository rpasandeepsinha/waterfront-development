<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

/**
 * Not all values are implemented yet. Check https://directadmin.com/api.html and search 'CMD_API_SHOW_USER_USAGE'
 * for the remaining possible data.
 *
 * @property string|int $bandwidth
 * @property string|int $quota
 * @property string|int $domainptr
 */
class ShowUserStats extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_USER_USAGE';

    protected string $method = 'GET';

    /**
     * @var array<int|string, string|int>
     */
    protected array $autoFormValues = [];

    /**
     * @var array <int|string|int, string[]>
     */
    protected array $autoDomainFormValues = [];

    /**
     * @var array|string[]
     */
    private array $keysUnlimited = ['bandwidth', 'quota', 'vdomains', 'nsubdomains', 'nemails', 'nemailf', 'nemailml', 'nemailr', 'mysql', 'domainptr', 'ftp'];

    /**
     * @var array|string[]
     */
    private array $keysOnOff = ['cgi', 'php', 'spam', 'cron', 'ssl', 'sysinfo', 'ssh', 'dnscontrol'];

    /**
     * @var string User to get data from.
     */
    private $user;

    /**
     * @var array<string, mixed>
     */
    private array $stats = [];

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->autoFormValues);
    }

    public function __set(string $name, string | int $value): void
    {
        if ((array_key_exists($name, $this->keysOnOff) && ($value === 'ON' || $value === 'OFF'))) {
            $this->autoFormValues[$name] = $value;
        }
        if (array_key_exists($name, $this->keysUnlimited) && (is_int($value) || ($value === 'ON' || $value === 'OFF'))) {
            $this->autoFormValues[$name] = $value;
        }
    }

    public function __get(string $name): string | int | null
    {
        if (array_key_exists('u' . $name, $this->autoFormValues)) {
            return $this->autoFormValues['u' . $name];
        }
        if (array_key_exists($name, $this->autoFormValues)) {
            return $this->autoFormValues[$name];
        }
        return null;
    }

    public function getBandwidth(): string | int
    {
        return $this->bandwidth;
    }

    public function getQuota(): string | int
    {
        return $this->quota;
    }

    public function getDomainPtr(): string | int
    {
        return $this->domainptr;
    }

    public function setBandwidth(string | int $bandwidth): ShowUserStats
    {
        $this->bandwidth = $bandwidth;
        return $this;
    }

    public function setQuota(string | int $quota): ShowUserStats
    {
        $this->quota = $quota;
        return $this;
    }

    public function setDomainPtr(string | int $domainptr): ShowUserStats
    {
        $this->domainptr = $domainptr;
        return $this;
    }

    public function setUser(string $user): ShowUserStats
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param mixed[] $decodedContent
     *
     * @throws DirectAdminCommandException
     */
    public function responseReceived(array $decodedContent): static
    {
        if (! array_key_exists('stats', $decodedContent)) {
            throw new DirectAdminCommandException(sprintf(
                "Unexpected response received from Directadmin in command %s. Missing 'stats' value",
                self::class
            ));
        }

        $stats = $decodedContent['stats'];
        assert(is_array($stats));
        $this->configureStats($stats);

        if (! array_key_exists('domains', $decodedContent)) {
            throw new DirectAdminCommandException(sprintf(
                "Unexpected response received from Directadmin in command %s. Missing 'domains' value",
                self::class
            ));
        }
        $domains = $decodedContent['domains'];
        assert(is_array($domains));
        $this->configureDomains($domains);

        $this->succeeded = true;

        return $this;
    }

    /**
     * @return array<mixed,mixed>
     */
    public function getDomainSettings(): array
    {
        return $this->autoDomainFormValues;
    }

    /**
     * @return array<mixed,mixed>
     */
    public function getUserSettings(): array
    {
        return $this->autoFormValues;
    }

    /**
     * @return array<mixed,mixed>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @throws DirectAdminCommandException
     */
    protected function createRequest(): Request
    {
        if ($this->user === null) {
            return parent::createRequest();
        }

        return new Request($this->getMethod(), $this->getUrl() . '&user=' . $this->user);
    }

    /**
     * @param array<mixed, mixed> $stats
     */
    private function configureStats(array $stats): void
    {
        foreach ($stats as $statSettings) {
            assert(is_array($statSettings));
            if (array_key_exists('setting', $statSettings)) {
                if (array_key_exists('usage', $statSettings)) {
                    $this->stats[$statSettings['setting']] = $statSettings['usage'];
                }
                if (in_array($statSettings['setting'], $this->keysUnlimited, true)) {
                    if ($statSettings['max_usage'] === 'unlimited') {
                        $this->autoFormValues['u' . $statSettings['setting']] = 'ON';
                        continue;
                    }
                    $this->autoFormValues[$statSettings['setting']] = $statSettings['max_usage'];
                    continue;
                }
                if (in_array($statSettings['setting'], $this->keysOnOff, true)) {
                    $this->autoFormValues[$statSettings['setting']] = $statSettings['usage'];
                }
            }
        }
    }

    /**
     * @param array<mixed,mixed> $domains
     */
    private function configureDomains(array $domains): void
    {
        foreach ($domains as $domain) {
            assert(is_array($domain));
            if (array_key_exists('domain', $domain)) {
                $this->autoDomainFormValues[$domain['domain']] = [];
                $this->autoDomainFormValues[$domain['domain']]['php'] = $domain['settings']['php'];
                $this->autoDomainFormValues[$domain['domain']]['ssl'] = $domain['settings']['ssl'];
                $this->autoDomainFormValues[$domain['domain']]['cgi'] = $domain['settings']['cgi'];
                if ($domain['bandwidth']['limit'] === 'shared') {
                    $this->autoDomainFormValues[$domain['domain']]['ubandwidth'] = 'ON';
                } else {
                    $this->autoDomainFormValues[$domain['domain']]['bandwidth'] = $domain['bandwidth']['limit'];
                }
                if ($domain['quota']['limit'] === 'shared') {
                    $this->autoDomainFormValues[$domain['domain']]['uquota'] = 'ON';
                } else {
                    $this->autoDomainFormValues[$domain['domain']]['quota'] = $domain['bandwidth']['limit'];
                }
            }
        }
    }
}
