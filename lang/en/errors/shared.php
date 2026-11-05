<?php

// Error messages by error code — Shared (any module).

return [
    'DOMAIN_ERROR' => 'The request could not be completed.',
    'CONFLICT' => 'The request conflicts with the current state of the record.',
    'FORBIDDEN' => 'You do not have permission to perform this action.',
    'UNAUTHENTICATED' => 'Please sign in to continue.',
    'NOT_FOUND' => 'The requested record was not found.',
    'UNKNOWN_RESOURCE' => 'Unknown resource.',
    'VALIDATION_FAILED' => 'Some fields are invalid.',
    'TOO_MANY_REQUESTS' => 'Too many requests. Please try again shortly.',
    'SERVER_ERROR' => 'Something went wrong on our side. Please try again in a moment.',
    'CONCURRENT_MODIFICATION' => 'This record was changed by someone else. Reload it and try again.',
    'INVALID_STATE_TRANSITION' => 'This action is not allowed in the current status.',
    'IDEMPOTENCY_KEY_REQUIRED' => 'An Idempotency-Key header is required for this request.',
    'IDEMPOTENCY_KEY_REUSED' => 'This Idempotency-Key was already used with a different request.',
    'SELF_APPROVAL_NOT_ALLOWED' => 'You cannot approve a request you created.',
    'BULK_ACTION_NOT_ALLOWED' => 'This bulk action is not allowed.',
];
