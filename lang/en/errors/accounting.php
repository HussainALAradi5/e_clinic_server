<?php

// Error messages by error code — Accounting.

return [
    'ACCOUNT_CODE_TAKEN' => 'This account code is already used.',
    'ACCOUNT_INVALID_PARENT' => 'The parent must be a group account of the same type, and not the account itself or one below it.',
    'ACCOUNT_HAS_TRANSACTIONS' => 'This account is used by other records. Archive it instead of deleting or retyping it.',
    'ACCOUNT_SYSTEM_PROTECTED' => 'System and mapped accounts cannot be deleted or archived.',
    'ACCOUNT_NOT_POSTABLE' => 'Choose an active, non-group account of the right type.',
    'FISCAL_YEAR_OVERLAP' => 'These dates overlap an existing fiscal year.',
    'FISCAL_PERIOD_CLOSED' => 'The accounting period of this date is closed. Use a date in an open period.',
    'FISCAL_PERIOD_MISSING' => 'There is no fiscal year for :date. Ask the accountant to create it.',
    'PREVIOUS_PERIOD_OPEN' => 'Close the earlier periods first; periods close in order.',
    'FISCAL_PERIOD_HAS_DRAFTS' => 'This period still has draft journal entries or open cash sessions. Post, delete or close them first.',
    'FISCAL_PERIOD_LOCKED' => 'This period is locked after the year-end audit and can never be reopened.',
    'LATER_PERIOD_CLOSED' => 'Reopen :period first; periods reopen latest first.',
    'JOURNAL_UNBALANCED' => 'Debits and credits must be equal and each line must have exactly one side.',
    'JOURNAL_LINE_ONE_SIDE' => 'Enter a debit or a credit on this line, not both.',
    'JOURNAL_NOT_EDITABLE' => 'Only draft manual entries can be edited, posted or deleted. Posted entries are reversed instead.',
    'JOURNAL_ALREADY_REVERSED' => 'This entry was already reversed.',
    'JOURNAL_AUTOMATIC_REVERSAL' => 'This entry was posted by a document. Cancel or void that document instead.',
    'JOURNAL_SELF_POST' => 'Entries above :limit must be posted by another accountant.',
    'REPORT_RANGE_TOO_LARGE' => 'Choose a period of at most :months months.',
];
