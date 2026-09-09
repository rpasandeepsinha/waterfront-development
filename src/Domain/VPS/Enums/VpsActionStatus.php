<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Enums;

/**
 * If a VPS action has been called in can be in different states.
 * This ENUM represents these possible states of the VPS.
 */
enum VpsActionStatus: string
{
    case REINSTALLING = 'reinstalling';
    case REINSTALLING_FAILED = 'reinstalling_failed';
    case REINSTALL_SUCCESS = 'reinstall_success';
    case RESET_CREDENTIALS_SUCCESS = 'reset_credentials_success';
    case RESETTING_CREDENTIALS = 'resetting_credentials';
    case RESET_CREDENTIALS_FAILED = 'reset_credentials_failed';
    case RESET_SSH_KEY_SUCCESS = 'reset_ssh_key_success';
    case RESETTING_SSH_KEY = 'resetting_ssh_key';
    case RESET_SSH_KEY_FAILED = 'reset_ssh_key_failed';
    case DELETING_FOR_REDEPLOY = 'deleting_for_redeploy';
    case DELETING_FOR_REDEPLOY_FAILED = 'deleting_for_redeploy_failed';
}
