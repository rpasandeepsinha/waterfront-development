<?php

declare(strict_types=1);

// phpcs:ignoreFile

namespace Tests\Infra\PowerDnsClient\Unit\Entities;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;

#[CoversClass(PowerDnsSecKeySet::class)]
class ResourceKeyTest extends TestCase
{
    #[Test]
    public function populateDnsSecKeySetFromStringSingle(): void
    {
        $response = $this->getPdnsKeyResponseSingle();
        $keySet = PowerDnsSecKeySet::fromString($response);
        $key = $keySet->findByType('csk');

        self::assertSame(
            '257 3 13 dEJhSgzfbrGERzigtB/Zua78N1z6q+1p7AQ0e0l0r0TD3ZTiKJB+iDOMdngKxh5anXtnu5Kled4kjupx3uqYJg==',
            $key->getDnsKey(),
        );
    }

    #[Test]
    public function populateDnsSecKeySetFromStringSingleExceptions(): void
    {
        $response = $this->getPdnsKeyResponseSingle();
        $keySet = PowerDnsSecKeySet::fromString($response);

        $this->expectException(RuntimeException::class);
        $keySet->findByType('cskyah');
    }

    #[Test]
    public function populateDnsSecKeySetFromStringMultiple(): void
    {
        $response = $this->getPdnsKeyResponseMulti();
        $keySet = PowerDnsSecKeySet::fromString($response);
        $key = $keySet->findByType('csk', 'RSASHA256');

        self::assertSame(
            '257 3 8 AwEAAct8h9u4jebS7xSbkmXWtIYMIjdZ8W/8UpUqrrLKd/Qd+ty0idXTlbfEWZ7e2Lop7RFQ3CkOaX3LMcSn8PRNEw5i5ThanQpYSZxpoTRKnDboVPa5Kl93RRdgcpuzaZADs6rHL14l+shruJkEBjS4xTI3cW6Z8GFuondMPW1I91kt',
            $key->getDnsKey(),
        );
    }

    #[Test]
    public function populateDnsSecKeySetFromStringMultiExceptions(): void
    {
        $response = $this->getPdnsKeyResponseMulti();
        $keySet = PowerDnsSecKeySet::fromString($response);

        $this->expectException(RuntimeException::class);
        $keySet->findByType('csk', 'notexisting');
    }

    private function getPdnsKeyResponseSingle(): string
    {
        return '[{"active": true, "algorithm": "ECDSAP256SHA256", "bits": 256, "dnskey": "257 3 13 dEJhSgzfbrGERzigtB/Zua78N1z6q+1p7AQ0e0l0r0TD3ZTiKJB+iDOMdngKxh5anXtnu5Kled4kjupx3uqYJg==", "ds": ["62046 13 1 faf24a756a8429e91177ca21a7da531ec583afe9", "62046 13 2 7bddadf781da57d60d2a5a7707921c5aed6d4eece32fce75077681570622376c", "62046 13 4 fc2edbaffdaedb49ee1f78429cfcb71b177aac1115b8a12312eb4c4414b73949f676f7c14b8024d515c24fef13623534"], "flags": 257, "id": 158515, "keytype": "csk", "type": "Cryptokey"}]';
    }

    private function getPdnsKeyResponseMulti(): string
    {
        return '[{"active": true, "algorithm": "ECDSAP256SHA256", "bits": 256, "dnskey": "257 3 13 kJugvFdAwIy1cLirD3H23rJuf8Ul1XFwponZ7y8qq7rMBN3/Hdvs9PnRTD6Hm4R8sANAh5Cqfn2EZcXvROIxLw==", "ds": ["44430 13 1 2d732d72778cf2e5948651e9a200131045378ccc", "44430 13 2 90dec6761b4d0fba3cb9f50423172b07e1391a421af5beab4b3829c2ccf827b1", "44430 13 4 1bfb7fd945bd669778a44047f5bfbacecf0a3ff95380c7a2483fc96d8fa4e1c22b7a3bc6825a28bbb3aec189e8a9b4f5"], "flags": 257, "id": 158513, "keytype": "csk", "type": "Cryptokey"}, {"active": false, "algorithm": "RSASHA256", "bits": 1024, "dnskey": "257 3 8 AwEAAct8h9u4jebS7xSbkmXWtIYMIjdZ8W/8UpUqrrLKd/Qd+ty0idXTlbfEWZ7e2Lop7RFQ3CkOaX3LMcSn8PRNEw5i5ThanQpYSZxpoTRKnDboVPa5Kl93RRdgcpuzaZADs6rHL14l+shruJkEBjS4xTI3cW6Z8GFuondMPW1I91kt", "ds": ["62686 8 1 17b54e0f857b9883cdac3c710fec955ed9fe9929", "62686 8 2 2ac8c798f552821258f4206ea1646104f285e35d6912ed2ed7e126525d9ccc4b", "62686 8 4 502eaa756c5747e9cfc1904864120e55c81b7cb3a20dab82ddaa9da912d2323f0db57afe31eb947bed0de1d99dc369da"], "flags": 257, "id": 158514, "keytype": "csk", "type": "Cryptokey"}]';
    }
}
