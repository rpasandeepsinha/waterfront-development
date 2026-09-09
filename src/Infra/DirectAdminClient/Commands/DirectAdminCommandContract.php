<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

interface DirectAdminCommandContract
{
    /**
     * Get the method for this command.
     *
     * @return string method of this command
     */
    public function getMethod(): string;

    /**
     * Get the request for this DirectAdmin Command. This
     * request is a PSR7 request and can be setup to use
     * the correct method and url and will be called
     * by the DirectAdminApi.
     */
    public function getRequest(): MessageInterface;

    /**
     * Get the command as documented in the DirectAdmin API.
     *
     * @return string Command from the DirectAdmin API
     */
    public function getCommand(): string;

    /**
     * Called on the command after the response from the DirectAdmin server
     * has been received and parsed. This method is useful to set data
     * accordingly on a command that extends this abstract class.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static;

    /**
     * Parse the response from a command on the DirectAdmin API.
     *
     *
     * @throws DirectAdminCommandException
     *
     * @return DirectAdminCommand FormValues as documented in the API
     */
    public function parseResponse(ResponseInterface $response): DirectAdminCommand;

    /**
     * When dynamically setting the properties make sure to use
     * camelcase when variables are longer then 2, otherwise
     * use lowercase, this will prevent rx turning into rX
     * and makes sure that db_quota will become dbQuota.
     *
     * @param string $property Property to transform in correct case
     *
     * @return string Correct cased property
     */
    public function caseProperty(string $property): string;

    /**
     * @param array<string, array<int, string>> $formValues
     */
    public function setFormValues(array $formValues): DirectAdminCommand;
}
