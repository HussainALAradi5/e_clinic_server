<?php

// Enum labels (enums.<EnumName>.<value>) — Communication.

return [
    'ConversationType' => ['direct' => 'One-to-one', 'group' => 'Group'],
    'MuteDuration' => ['8h' => '8 hours', '1w' => '1 week', 'always' => 'Until I unmute'],
    'ParticipantRole' => ['admin' => 'Leader', 'member' => 'Member'],
    'MessageKind' => ['text' => 'Text', 'media' => 'Photo / file', 'voice' => 'Voice note'],
    'BubbleStyle' => ['rounded' => 'Rounded', 'square' => 'Square', 'minimal' => 'Minimal'],
    'BubbleColor' => ['blue' => 'Blue', 'green' => 'Green', 'teal' => 'Teal', 'purple' => 'Purple', 'rose' => 'Rose', 'slate' => 'Slate'],
    'TicketConfirmation' => ['awaiting' => 'Awaiting confirmation', 'confirmed' => 'Confirmed fixed', 'declined' => 'Declined — not fixed'],
    'TicketCategory' => ['technical' => 'Technical problem', 'access' => 'Access & accounts', 'equipment' => 'Equipment', 'data' => 'Wrong data', 'other' => 'Other'],
];
