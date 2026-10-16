<?php

/*
| Notification templates — minimum PHI (no diagnoses; "your result is ready" + portal link).
*/

return [
    'task' => [
        'completed' => [
            'subject' => ':task finished',
            'body' => 'Your task ":task" finished successfully.',
        ],
        'failed' => [
            'subject' => ':task failed',
            'body' => 'Your task ":task" could not be completed: :reason',
        ],
    ],
];
