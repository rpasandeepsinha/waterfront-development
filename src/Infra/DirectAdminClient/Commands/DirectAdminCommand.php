<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

abstract class DirectAdminCommand implements DirectAdminCommandContract
{
    /**
     * @var string Direct Admin Command string
     */
    protected string $command;

    /**
     * @var bool If the commands want json as response.
     */
    protected bool $useJsonResponse = true;

    /**
     * @var bool If we need to parse the response from url encoding.
     */
    protected bool $urlDecode = false;

    /**
     * @var string Method to use when requesting from the API
     */
    protected string $method = 'GET';

    /**
     * @var mixed[] Raw Values from the API
     */
    protected array $formValues;

    /**
     * @var string Return string of the API when there is a failure.
     */
    protected string $failureString = 'error=1';

    /**
     * @var string Response body from API request
     */
    protected $responseBody;

    /**
     * Result of API command.
     *
     * @var string Sentence(s) with what happened.
     */
    protected string $result;

    /**
     * Command has been send and received success response.
     */
    protected bool $succeeded = false;

    /**
     * Parse the response from a command on the DirectAdmin API.
     *
     * @throws DirectAdminCommandException|JsonException
     */
    public function parseResponse(ResponseInterface $response): static
    {
        $this->responseBody = $response->getBody()->getContents();

        if ($this->urlDecode && Str::contains($this->responseBody, $this->failureString)) {
            throw new DirectAdminCommandException(
                "DirectAdmin returned a Failure on {$this->getCurrentCommand()}," . 'Response: '
                    . rawurldecode($this->responseBody),
            );
        }

        $decoded = $this->decodeResponse($this->responseBody);

        if ($this->usesJsonResponse() && Arr::has($decoded, 'error')) {
            if ($decoded['error'] !== '0') {
                $result = array_key_exists('result', $decoded) ? $decoded['result'] : '';
                $error = $decoded['error'];
                assert(is_string($error));
                throw new DirectAdminCommandException(
                    "Failed [{$this->getCurrentCommand()}]: {$error} - {$result}",
                );
            }
        }

        $this->setFormValues($decoded);

        return $this->responseReceived($decoded);
    }

    /**
     * Check if this commands wants json as response.
     *
     * @return bool true if commands wants json.
     */
    public function usesJsonResponse(): bool
    {
        return $this->useJsonResponse;
    }

    /**
     * Decode url encoded responses from DirectAdmin.
     *
     * @return mixed[] decoded values from url encoded input
     */
    public function decodeUrlEncodedString(string $encoded): array
    {
        parse_str($encoded, $decoded);

        return $decoded;
    }

    /**
     * Called on the command after the response from the DirectAdmin server
     * has been received and parsed. This method is useful to set data
     * accordingly on a command that extends this abstract class.
     * It will set the data to properties by default.
     */
    public function responseReceived(array $decodedContent): static
    {
        foreach ($decodedContent as $name => $value) {
            if (is_int($name)) {
                $name = (string) $name;
            }

            $this->{$this->caseProperty($name)} = $value;
        }

        $this->succeeded = true;

        return $this;
    }

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
    public function caseProperty(string $property): string
    {
        return Str::length($property) > 2 ? Str::camel($property) : Str::lower($property);
    }

    /**
     * Get the Request to use with Guzzle Client.
     */
    public function getRequest(): Request
    {
        return $this->createRequest();
    }

    /**
     * Get the method for this command.
     *
     * @return string method of this command
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the url for this command to send to the API.
     *
     * @return string the relative url with the command name
     */
    public function getUrl(): string
    {
        return $this->getCommand() . ($this->usesJsonResponse() ? '?json=yes' : '');
    }

    /**
     * Get the command as documented in the DirectAdmin API.
     *
     * @return string Command from the DirectAdmin API
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the raw response values from DirectAdmin API.
     *
     * @return mixed[] Form values from the API
     */
    public function getFormValues(): array
    {
        return $this->formValues;
    }

    /**
     * @return string Results of user creation
     */
    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * Check if command has been send and received success response.
     */
    public function hasSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function setSucceeded(bool $success): void
    {
        $this->succeeded = $success;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Set the form values for this command.
     *
     * @param mixed[] $formValues
     */
    final public function setFormValues(array $formValues): DirectAdminCommand
    {
        $this->formValues = $formValues;

        return $this;
    }

    /**
     * Decode the response using json or url encoding.
     *
     * @return mixed[] Decoded values
     */
    protected function decodeResponse(string $response): array
    {
        if ($this->usesJsonResponse()) {
            $jsonDecoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (is_null($jsonDecoded)) {
                return [];
            }

            return is_array($jsonDecoded) ? $jsonDecoded : ['response' => $jsonDecoded];
        }

        return $this->decodeUrlEncodedString($response);
    }

    /**
     * Create a PSR7 Request to be used by Guzzle, containing the
     * configurations for this DirectAdminCommand. Overriding
     * this class gives access to e.g. setting the POST data.
     *
     * @return Request Command specific request being send to the DirectAdminApi
     */
    protected function createRequest(): Request
    {
        return new Request($this->getMethod(), $this->getUrl());
    }

    /**
     * Get the current command name from the class
     * instance without full namespace.
     *
     * @return string Command Name
     */
    private function getCurrentCommand(): string
    {
        return new ReflectionClass($this)->getShortName();
    }
}
