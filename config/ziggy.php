<?php

return [
    'only' => [
        'dashboard.*',
        'aliases.*',
        'recipients.*',
        'domains.*',
        'usernames.*',
        'domains.*',
        'blocklist.*',
        'failed_deliveries.*',
        'rules.*',
        'settings.*',
        'webauthn.create',
        'verification.notice',
        'verification.resend',
        'logout',
        'account.destroy',
    ],
];
