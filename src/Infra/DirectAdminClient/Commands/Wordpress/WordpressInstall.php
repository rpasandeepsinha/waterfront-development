<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Wordpress;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Softaculous;

class WordpressInstall extends DirectAdminCommand implements Softaculous
{
    /**
     * @var string Command from DirectAdminApi
     *
     * 26 is the wordpress identifier.
     */
    protected string $command = 'CMD_PLUGINS/softaculous/index.raw?act=software&soft=26&jsnohf=1&soft=26';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    protected string $domain;

    protected string $email;

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): WordpressInstall
    {
        $this->domain = $domain;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): WordpressInstall
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Create a 'Plugin' request to be send to the api.
     */
    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     *
     * List of potential settings.
     *
     *  softbranch=26
     * &softproto=1
     * &softdomain=test-domain.nl
     * &softdirectory=wp
     * &site_name=My+Blog
     * &site_desc=My+WordPress+Blog
     * &admin_username=admin
     * &admin_pass=pass
     * &admin_email=admin%40test-domain.nl
     * &language=en&softdb=wp274
     * &dbprefix=wp8e_&eu_auto_upgrade=0
     * &backup_location=0
     * &auto_backup=0
     * &autobackup_cron_min=
     * &autobackup_cron_hour=
     * &autobackup_cron_day=
     * &autobackup_cron_month=
     * &autobackup_cron_weekday=
     * &theme_id=
     * &theme_name=
     * &softsubmit=Install
     * &pass-strength-hidden=18
     * &emailto=
     * &soft_status_key=status_key
     */
    private function getPostBody(): StreamInterface
    {
        $body = [
            'softsubmit' => 'Install',
            'softdomain' => $this->getDomain(),
            'softdirectory' => 'wp', // Keep empty to install in Web Root
            'softdb' => 'wpdb',
            'admin_username' => 'admin',
            'admin_pass' => $this->generatePassword(),
            'admin_email' => $this->getEmail(),
            'emailto' => $this->getEmail(),
            'language' => 'en',
            'site_name' => 'WordPress Site',
            'site_desc' => 'My Site',
            'dbprefix' => 'wp_',
        ];

        return Utils::streamFor(http_build_query($body));
    }

    /**
     * Generate a password (extracted from illuminate support).
     *
     * @see https://github.com/illuminate/support/blob/master/Str.php
     *
     * @throws Exception
     */
    private function generatePassword(int $length = 16): string
    {
        $password = '';

        while (($len = strlen($password)) < $length) {
            $size = $length - $len;

            assert($size > 0);

            $bytes = random_bytes($size);

            $password .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $password;
    }
}
