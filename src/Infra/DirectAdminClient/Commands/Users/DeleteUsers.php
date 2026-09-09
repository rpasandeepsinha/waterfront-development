<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DeleteUsers extends DirectAdminCommand
{
    protected string $method = 'POST';

    protected string $command = 'CMD_API_SELECT_USERS';

    /**
     * @var mixed[] Username(s) to delete from the server.
     */
    private array $usernames;

    /**
     * @param mixed[] $usernames
     */
    public function setUsernames(array $usernames): DeleteUsers
    {
        $this->usernames = $usernames;
        return $this;
    }

    /**
     * Add a user to delete.
     *
     * @param string $username Username to delete
     */
    public function addUser(string $username): DeleteUsers
    {
        $this->usernames[] = $username;
        return $this;
    }

    /**
     * Get an array with usernames to delete.
     *
     * @return mixed[] usernames prepared to delete from the directadmin server.
     */
    public function getUsers(): array
    {
        return $this->usernames;
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
        $params = [
                'confirmed' => 'Confirm',
                'delete' => 'yes',
            ] + $this->getUsersToDelete();

        return Utils::streamFor(http_build_query($params));
    }

    /**
     * Get the users to delete from the server.
     *
     * @return mixed[] with usernames formatted for the DirectAdmin Api
     */
    private function getUsersToDelete(): array
    {
        $toDelete = [];
        $i = 0;

        foreach ($this->usernames as $user) {
            $toDelete['select' . $i] = $user;
            $i++;
        }

        return $toDelete;
    }
}
