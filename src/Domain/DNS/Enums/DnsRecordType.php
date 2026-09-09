<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Enums;

enum DnsRecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case ALIAS = 'ALIAS';
    case CAA = 'CAA';
    case CDS = 'CDS';
    case CNAME = 'CNAME';
    case DNAME = 'DNAME';
    case DS = 'DS';
    case KEY = 'KEY';
    case LOC = 'LOC';
    case MX = 'MX';
    case NAPTR = 'NAPTR';
    case NS = 'NS';
    case OPENPGPKEY = 'OPENPGPKEY';
    case PTR = 'PTR';
    case RP = 'RP';
    case SPF = 'SPF';
    case SRV = 'SRV';
    case SSHFP = 'SSHFP';
    case TLSA = 'TLSA';
    case TXT = 'TXT';
    case WKS = 'WKS';
    case DNSKEY = 'DNSKEY';
    case NSEC = 'NSEC';
    case NSEC3 = 'NSEC3';
    case NSEC3PARAM = 'NSEC3PARAM';
    case RRSIG = 'RRSIG';
    case URI = 'URI';
}
