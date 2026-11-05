<?php

// Notification wording (notifications.<group>.<event>.<part>) — Identity & System.

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
