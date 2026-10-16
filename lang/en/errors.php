<?php

/*
| Messages for stable error codes (the client switches on error_code, never on these texts).
*/

return [
    'DOMAIN_ERROR' => 'The request could not be completed.',
    'CONFLICT' => 'The request conflicts with the current state of the record.',
    'FORBIDDEN' => 'You do not have permission to perform this action.',
    'UNAUTHENTICATED' => 'Please sign in to continue.',
    'NOT_FOUND' => 'The requested record was not found.',
    'UNKNOWN_RESOURCE' => 'Unknown resource.',
    'VALIDATION_FAILED' => 'Some fields are invalid.',
    'TOO_MANY_REQUESTS' => 'Too many requests. Please try again shortly.',
    'SERVER_ERROR' => 'Something went wrong. Please try again or contact support with the request id.',
    'CONCURRENT_MODIFICATION' => 'This record was changed by someone else. Reload it and try again.',
    'INVALID_STATE_TRANSITION' => 'This action is not allowed in the current status.',
    'IDEMPOTENCY_KEY_REQUIRED' => 'An Idempotency-Key header is required for this request.',
    'IDEMPOTENCY_KEY_REUSED' => 'This Idempotency-Key was already used with a different request.',
    'SELF_APPROVAL_NOT_ALLOWED' => 'You cannot approve a request you created.',
    'BULK_ACTION_NOT_ALLOWED' => 'This bulk action is not allowed.',

    // Identity
    'AUTH_INVALID_CREDENTIALS' => 'The email or password is incorrect.',
    'AUTH_ACCOUNT_LOCKED' => 'This account is locked. Try again later or contact an administrator.',
    'AUTH_ACCOUNT_INACTIVE' => 'This account is not active.',
    'AUTH_RESET_TOKEN_INVALID' => 'This password reset link is invalid or has expired.',
    'AUTH_CURRENT_PASSWORD_WRONG' => 'The current password is incorrect.',
    'AUTH_PASSWORD_REUSED' => 'Choose a password you have not used recently.',
    'AUTH_2FA_REQUIRED' => 'Two-factor authentication is required.',
    'AUTH_2FA_INVALID' => 'The verification code is invalid or has expired.',
    'AUTH_2FA_ALREADY_ENABLED' => 'Two-factor authentication is already enabled.',
    'USER_EMAIL_TAKEN' => 'Another person already uses this email.',
    'USER_CANNOT_MODIFY_SELF' => 'You cannot change the status or roles of your own account.',
    'USER_LAST_SUPER_ADMIN' => 'At least one active super admin must remain.',
    'ROLE_SYSTEM_PROTECTED' => 'System roles cannot be renamed, deleted or have this change.',
    'ROLE_IN_USE' => 'This role is still assigned to users.',
    'ROLE_PRIVILEGE_ESCALATION' => 'You cannot grant permissions or roles you do not hold yourself.',
    'PERSON_DUPLICATE' => 'This person already exists. Use the existing record.',

    // Identity › Tasks, Attachments, Codes, Notifications
    'TASK_NOT_CANCELLABLE' => 'This task has already finished.',
    'TASK_RESULT_NOT_READY' => 'The result of this task is not ready yet.',
    'TASK_RESULT_EXPIRED' => 'The result of this task has expired. Run it again.',
    'ATTACHMENT_TYPE_NOT_ALLOWED' => 'This file type or size is not allowed for this category.',
    'ATTACHMENT_NOT_READY' => 'This file is still being checked or was blocked by the virus scan.',
    'ATTACHMENT_DUPLICATE' => 'This file is already attached.',
    'UPLOAD_SESSION_INVALID' => 'This upload session is invalid, expired or out of sync.',
    'UPLOAD_LIMIT_EXCEEDED' => 'Too many files or the files are too large for one request.',
    'SCAN_CODE_INVALID' => 'This code is not valid or was not issued by this clinic.',
    'INVALID_WEBHOOK_SIGNATURE' => 'Invalid webhook signature.',
    'NOTIFICATION_NOT_RESENDABLE' => 'Only failed or cancelled notifications can be resent.',
    'NOTIFICATION_NOT_CANCELLABLE' => 'Only scheduled notifications can be cancelled.',
    'SAVED_VIEW_NAME_TAKEN' => 'You already have a view with this name.',

    // Reference Data
    'ADDRESS_INVALID' => 'The address is incomplete for this country (Bahrain needs building, road and block).',
    'COMPANY_IN_USE' => 'This company is still used by other records. Archive it instead.',
];
