<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services;

use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingUsernameInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class PleskUsernameBroker implements HostingUsernameInterface
{
    /** @var string[] */
    private array $prohibitedUsernames = [
        'root',
        'Administrator',
    ];

    public function __construct(
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * Generates a unique customer username for Plesk that fits the length constraint (20 characters).
     *
     * Plesk username limitations as defined by plesk:
     *
     * @see https://support.plesk.com/hc/en-us/articles/213395409-What-limits-are-set-in-Plesk-for-username-password-
     *
     * - Plesk username length: from 1 to 255 symbols;
     * - The first character is limited to alphanumeric characters.
     * - "" (Space) is prohibited.
     * - System usernames on the OS (Linux "root" and Windows "Administrator") are also prohibited.
     * - The first character is limited to alphanumeric characters.
     * - Characters from all languages are allowed.
     * - Special sentences other than (& @.'+ -) are prohibited.
     * - System usernames on the OS (Linux "root" and Windows "Administrator") are also prohibited
     */
    public function generateUsername(): string
    {
        $usernameMaxLength = $this->configuration->getAsInteger('hostingservice.plesk.username_max_length');

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $username = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $usernameMaxLength; $i++) {
            $username .= $characters[random_int(0, $max)];
        }

        assert($username !== '');

        if (in_array($username, $this->prohibitedUsernames, true)) {
            return $this->generateUsername();
        }

        return $username;
    }
}
