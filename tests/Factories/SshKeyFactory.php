<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\RSA;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\VPS\Models\SshKey;

/**
 * @extends Factory<SshKey>
 */
class SshKeyFactory extends Factory
{
    protected $model = SshKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sshKey = RSA::createKey()->getPublicKey();
        assert($sshKey instanceof PublicKey);

        return [
            'uuid' => Uuid::uuid4(),
            'key_name' =>  $this->faker->word(),
            'public_key' => base64_encode($sshKey->toString('OpenSSH')),
            'fingerprint' => $sshKey->getFingerprint('md5'),
            'cloudstack_ssh_name'  => sha1($sshKey->toString('OpenSSH')),
        ];
    }
}
