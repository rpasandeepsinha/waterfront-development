<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowResellerConfig extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_RESELLER_CONFIG';

    protected bool $useJsonResponse = true;

    /**
     * @var string The Resellers resellername. 4-8 characters, alphanumeric
     */
    private string $reseller = '';

    /**
     * Reseller config retrieved from the API.
     *
     * @var mixed[]
     */
    private $resellerConfig = [];

    /**
     * @return string The Reseller's resellername. 4-8 characters, alphanumeric
     */
    public function getReseller(): string
    {
        return $this->reseller;
    }

    /**
     * @param string $reseller The Reseller's resellername. 4-8 characters, alphanumeric
     */
    public function setReseller(string $reseller): ShowResellerConfig
    {
        $this->reseller = $reseller;

        return $this;
    }

    /**
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->resellerConfig = $decodedContent;

        return parent::responseReceived($decodedContent);
    }

    /**
     * @return mixed[]
     */
    public function getResellerConfig(): array
    {
        return $this->resellerConfig;
    }

    /**
     * @param array<string,string> $resellerConfig
     */
    public function setResellerConfig(array $resellerConfig): ShowResellerConfig
    {
        $this->resellerConfig = $resellerConfig;

        return $this;
    }
}
