<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Enums;

use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingSsoRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365DeleteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetVerificationDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365SetDomainAsDefaultDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;

enum ProvisionRequestName: string
{
    case DECOUPLE_DOMAIN = 'decouple_domain';
    case COUPLE_DOMAIN = 'couple_domain';
    case GET_HOSTING_SSO = 'get_hosting_sso';
    case CREATE_HOSTING = 'create_hosting';
    case CREATE_REDIRECT = 'create_redirect';
    case GET_MICROSOFT365_AUTHORIZATION_URL = 'get_microsoft365_authorization_url';
    case GET_MICROSOFT365_TENANT_NAME = 'get_microsoft365_tenant_name';
    case GET_MICROSOFT365_DOMAIN = 'get_microsoft365_domain';
    case CREATE_MICROSOFT365_DOMAIN = 'create_microsoft365_domain';
    case VERIFY_MICROSOFT365_DOMAIN = 'verify_microsoft365_domain';
    case PROMOTE_MICROSOFT365_DOMAIN = 'promote_microsoft365_domain';
    case DELETE_MICROSOFT365_DOMAIN = 'delete_microsoft365_domain';
    case GET_SERVICE_DNS_RECORDS_REQUEST = 'get_service_dns_records_request';
    case GET_VERIFICATION_DNS_RECORDS_REQUEST = 'get_verification_dns_records_request';
    case GET_REDIRECT = 'get_redirect';
    case LIST_REDIRECTS = 'list_redirects';
    case DELETE_REDIRECT = 'delete_redirect';
    case UPDATE_REDIRECT = 'update_redirect';
    case TERMINATE_REDIRECTS = 'terminate_redirects';
    case CREATE_SITEBUILDER = 'create_sitebuilder';
    case CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION = 'create_basekit_deployments_from_migration';
    case ROLLBACK_BASEKIT_DEPLOYMENTS_FROM_MIGRATION = 'rollback_basekit_deployments_from_migration';
    case GET_SITEBUILDER_SSO = 'get_sitebuilder_sso';
    case ADD_SSL_SITEBUILDER = 'add_ssl_sitebuilder';
    case UPDATE_SITEBUILDER = 'update_sitebuilder';
    case TERMINATE_SITEBUILDER_CONTEXT = 'terminate_sitebuilder_context';
    case TERMINATE_SITEBUILDER_SITE = 'terminate_sitebuilder_site';
    case GET_BASEKIT_SITE_BY_REF_REQUEST = 'get_basekit_site_by_ref_request';
    case GET_BASEKIT_USER_BY_REF_REQUEST = 'get_basekit_user_by_ref_request';
    case CREATE_BACKUP = 'create_backup';
    case CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION = 'create_backup_deployments_from_migration';
    case GET_BACKUP_SSO_REQUEST = 'get_backup_sso_request';
    case TERMINATE_BACKUP = 'terminate_backup';
    case UPDATE_BACKUP = 'update_backup';
    case SET_BACKUP_SUSPENSION_STATE = 'set_backup_suspension_state';
    case GET_BACKUP_USAGE = 'get_backup_usage';
    case SET_MICROSOFT365_DOMAIN_AS_DEFAULT = 'set_microsoft365_domain_as_default';
    case SUSPEND_REDIRECT = 'suspend_redirect';
    case UNSUSPEND_REDIRECT = 'unsuspend_redirect';

    public function isCreateRequest(): bool
    {
        return in_array($this, self::getCreateRequests(), true);
    }

    /** @return self[] */
    public static function getCreateRequests(): array
    {
        return [
            self::CREATE_HOSTING,
            self::CREATE_REDIRECT,
            self::CREATE_MICROSOFT365_DOMAIN,
            self::CREATE_SITEBUILDER,
            self::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION,
            self::CREATE_BACKUP,
            self::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION,
        ];
    }

    /**
     * @return class-string<ProvisionRequestInterface>
     */
    public function toRequestClass(): string
    {
        return match ($this) {
            self::DECOUPLE_DOMAIN => DomainNameDecoupleRequest::class,
            self::COUPLE_DOMAIN => DomainNameCoupleRequest::class,
            self::GET_HOSTING_SSO => HostingSsoRequest::class,
            self::CREATE_HOSTING => HostingCreateRequest::class,
            self::CREATE_REDIRECT => CreateRedirectRequest::class,
            self::GET_MICROSOFT365_AUTHORIZATION_URL => Microsoft365AuthorizationUrlRequest::class,
            self::GET_MICROSOFT365_TENANT_NAME => Microsoft365TenantIdRequest::class,
            self::GET_MICROSOFT365_DOMAIN => Microsoft365GetDomainRequest::class,
            self::CREATE_MICROSOFT365_DOMAIN => Microsoft365CreateDomainRequest::class,
            self::VERIFY_MICROSOFT365_DOMAIN => Microsoft365VerifyDomainRequest::class,
            self::PROMOTE_MICROSOFT365_DOMAIN => Microsoft365PromoteDomainRequest::class,
            self::DELETE_MICROSOFT365_DOMAIN => Microsoft365DeleteDomainRequest::class,
            self::GET_SERVICE_DNS_RECORDS_REQUEST => Microsoft365GetServiceDnsRecordsRequest::class,
            self::GET_VERIFICATION_DNS_RECORDS_REQUEST => Microsoft365GetVerificationDnsRecordsRequest::class,
            self::GET_REDIRECT => GetRedirectRequest::class,
            self::LIST_REDIRECTS => ListRedirectsRequest::class,
            self::DELETE_REDIRECT => DeleteRedirectRequest::class,
            self::UPDATE_REDIRECT => UpdateRedirectRequest::class,
            self::TERMINATE_REDIRECTS => TerminateRedirectsRequest::class,
            self::SUSPEND_REDIRECT => SuspendRedirectRequest::class,
            self::UNSUSPEND_REDIRECT => UnsuspendRedirectRequest::class,
            self::CREATE_SITEBUILDER => CreateSitebuilderRequest::class,
            self::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION => CreateBasekitDeploymentsFromMigrationRequest::class,
            self::ROLLBACK_BASEKIT_DEPLOYMENTS_FROM_MIGRATION => RollbackBasekitDeploymentsFromMigrationRequest::class,
            self::GET_SITEBUILDER_SSO => GetSitebuilderSsoRequest::class,
            self::ADD_SSL_SITEBUILDER => AddSslSitebuilderRequest::class,
            self::UPDATE_SITEBUILDER => UpdateSitebuilderRequest::class,
            self::TERMINATE_SITEBUILDER_CONTEXT => TerminateSitebuilderContextRequest::class,
            self::TERMINATE_SITEBUILDER_SITE => TerminateSitebuilderRequest::class,
            self::GET_BASEKIT_SITE_BY_REF_REQUEST => GetBasekitSiteByRefRequest::class,
            self::GET_BASEKIT_USER_BY_REF_REQUEST => GetBasekitUserByRefRequest::class,
            self::CREATE_BACKUP => CreateBackupRequest::class,
            self::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION => CreateBackupDeploymentsFromMigrationRequest::class,
            self::GET_BACKUP_SSO_REQUEST => GetBackupSsoRequest::class,
            self::TERMINATE_BACKUP => TerminateBackupRequest::class,
            self::UPDATE_BACKUP => UpdateBackupRequest::class,
            self::SET_BACKUP_SUSPENSION_STATE => SetBackupSuspensionStateRequest::class,
            self::GET_BACKUP_USAGE => GetBackupUsageRequest::class,
            self::SET_MICROSOFT365_DOMAIN_AS_DEFAULT => Microsoft365SetDomainAsDefaultDomainRequest::class,
        };
    }
}
