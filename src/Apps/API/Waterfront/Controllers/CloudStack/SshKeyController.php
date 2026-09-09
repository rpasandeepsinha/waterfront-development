<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers\CloudStack;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use Waterfront\Apps\API\Waterfront\Requests\CloudStack\CreateSshKeyRequest;
use Waterfront\Apps\API\Waterfront\Resources\CloudStack\SshKeyResource;
use Waterfront\Domain\VPS\Actions\DeleteSshKeyAction;
use Waterfront\Domain\VPS\Exceptions\SshKeyNotDeletableException;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class SshKeyController
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly SshKeyRepository $sshKeyRepository,
        private readonly DeleteSshKeyAction $deleteSshKeyAction,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $sshKeys = $this->sshKeyRepository->findAllByCustomer($customer);

        return SshKeyResource::collection($sshKeys);
    }

    public function create(CreateSshKeyRequest $request): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $sshKeyString = (string) $request->string('ssh_key');
        $sshKeyNameString = (string) $request->string('key_name');

        try {
            $publicKey = PublicKeyLoader::loadPublicKey($sshKeyString);
            $fingerprint = $publicKey->getFingerprint('md5');

            if ($this->sshKeyRepository->keyExists($customer->id, $fingerprint)) {
                return new JsonResponse(
                    [
                        'message' => $this->translator->translate('message.error.validation'),
                        'errors' => [
                            'ssh_key' => [$this->translator->translate('ssh-key.not-unique')],
                        ],
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            /**
             * Not all public keys support comments, so we need to check if the method exists.
             *
             * @see https://phpseclib.com/docs/publickeys#comments
             */
            $comment = method_exists($publicKey, 'getComment') ? $publicKey->getComment() : null;

            $sshKey = $this->sshKeyRepository->saveSshKey(
                $customer,
                $sshKeyNameString,
                $publicKey->toString('OpenSSH', ['comment' => $comment]),
                $fingerprint
            );

            return new JsonResponse([
                'uuid' => $sshKey->uuid,
                'key_name' => $sshKey->key_name,
                'fingerprint' => $sshKey->fingerprint,
            ], Response::HTTP_CREATED);
        } catch (NoKeyLoadedException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('message.error.validation'),
                    'errors' => [
                        'ssh_key' => [$this->translator->translate('ssh-key.invalid-pubkey-format')],
                    ],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function destroy(SshKey $sshKey): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $errorMessage = sprintf(
            '%s %s',
            $sshKey->key_name,
            $this->translator->translate('ssh-key.delete.error')
        );

        try {
            return $this->deleteSshKeyAction->execute($sshKey, $customer)
                ? new JsonResponse([], Response::HTTP_NO_CONTENT)
                : new JsonResponse(['message' => $errorMessage], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SshKeyNotDeletableException) {
            $errorMessage = sprintf(
                '%s %s',
                $sshKey->key_name,
                $this->translator->translate('ssh-key.not-deletable.error')
            );

            return new JsonResponse(['message' => $errorMessage], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
