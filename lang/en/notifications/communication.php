<?php

// Notification wording (notifications.<group>.<event>.<part>) — Communication.

return [
    'chat' => [
        'added' => [
            'subject' => 'Added to :chat',
            'body' => ':by added you to the group :chat.',
        ],
        'removed' => [
            'subject' => 'Removed from :chat',
            'body' => ':by removed you from the group :chat.',
        ],
        'role' => [
            'subject' => 'You are now :role in :chat',
            'body' => ':by made you :role of the group :chat.',
        ],
        'mention' => [
            'subject' => ':by mentioned you',
            'body' => ':by mentioned you in :chat: :excerpt',
        ],
    ],
    'ticket' => [
        'opened' => [
            'subject' => 'New support ticket :code',
            'body' => 'New support ticket :code: :subject',
        ],
        'updated' => [
            'subject' => 'Ticket :code is :status',
            'body' => 'Your support ticket :code (:subject) is now :status.',
        ],
        'resolved' => [
            'subject' => 'Ticket :code resolved',
            'body' => "Your support ticket :code (:subject) is solved. What was done:\n:resolution\nPlease confirm it works, or decline if it does not.",
        ],
        'confirmed' => [
            'subject' => 'Ticket :code confirmed fixed',
            'body' => 'The requester confirmed support ticket :code (:subject) is fixed.',
        ],
        'declined' => [
            'subject' => 'Ticket :code is not fixed',
            'body' => 'The requester declined the fix for support ticket :code (:subject); it is back in progress.',
        ],
        'declined_comment' => 'Not fixed yet: :reason',
        'replied' => [
            'subject' => 'New reply on ticket :code',
            'body' => 'There is a new reply on support ticket :code (:subject).',
        ],
    ],
];
